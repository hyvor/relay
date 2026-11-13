<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Controller\SendsController;
use App\Entity\Project;
use App\Entity\Type\DomainStatus;
use App\Entity\Type\KycStatus;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\KycFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use Hyvor\Internal\Billing\BillingFake;
use Hyvor\Internal\Billing\License\RelayLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(SendsController::class)]
class SendEmailCloudTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    private function createProject(): Project
    {
        InstanceFactory::new()->withDefaultDkim()->create();
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            'project' => $project,
            'domain' => 'hyvor.com',
            'status' => DomainStatus::ACTIVE,
        ]);

        return $project;
    }

    private function fakeLicense(ResolvedLicenseType $type): void
    {
        BillingFake::enableForSymfony(
            $this->container,
            [1 => new ResolvedLicense($type, RelayLicense::trial())]
        );
    }

    public function test_allows_sending_to_simulator_without_kyc(): void
    {
        $project = $this->createProject();

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'accept@simulator.relay.hyvor.com',
            'cc' => 'missing@simulator.relay.hyvor.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    public function test_does_not_allow_sending_to_others_without_kyc(): void
    {
        $project = $this->createProject();

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'accept@simulator.relay.hyvor.com',
            'bcc' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(
            'Emails can only be sent to @simulator.relay.hyvor.com until your KYC is approved and your subscription is active',
            $this->getJson()['message']
        );
    }

    public function test_does_not_allow_sending_with_rejected_kyc(): void
    {
        $project = $this->createProject();
        KycFactory::createOne(['organization_id' => 1, 'status' => KycStatus::REJECTED]);
        $this->fakeLicense(ResolvedLicenseType::SUBSCRIPTION);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_does_not_allow_sending_during_trial(): void
    {
        $project = $this->createProject();
        KycFactory::createOne(['organization_id' => 1, 'status' => KycStatus::APPROVED]);
        $this->fakeLicense(ResolvedLicenseType::TRIAL);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    #[TestWith([ResolvedLicenseType::SUBSCRIPTION])]
    #[TestWith([ResolvedLicenseType::ENTERPRISE_CONTRACT])]
    public function test_allows_sending_with_approved_kyc_and_subscription(ResolvedLicenseType $type): void
    {
        $project = $this->createProject();
        KycFactory::createOne(['organization_id' => 1, 'status' => KycStatus::APPROVED]);
        $this->fakeLicense($type);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(200);
    }
}
