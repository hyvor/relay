<?php

namespace App\Tests\Service\Cloud;

use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\Type\DomainStatus;
use App\Entity\Type\KycStatus;
use App\Entity\Type\QueueType;
use App\Service\App\Config;
use App\Service\Cloud\CloudProjectQueueListener;
use App\Service\Instance\InstanceService;
use App\Service\Kyc\KycService;
use App\Service\Queue\ProjectQueueResolver;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\KycFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use Hyvor\Internal\Billing\BillingFake;
use Hyvor\Internal\Billing\License\RelayLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CloudProjectQueueListener::class)]
#[CoversClass(ProjectQueueResolver::class)]
#[CoversClass(KycService::class)]
class CloudQueueRoutingTest extends WebTestCase
{

    private Queue $transactionalDefault;
    private Queue $transactionalTrial;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';

        $this->transactionalDefault = QueueFactory::createTransactional();
        $this->transactionalTrial = QueueFactory::createOne([
            'type' => QueueType::TRANSACTIONAL,
            'name' => CloudProjectQueueListener::TRIAL_QUEUE_NAME,
        ]);
    }

    private function setLicense(int $organizationId, ResolvedLicenseType $type): void
    {
        /** @var BillingFake $billing */
        $billing = $this->container->get(BillingFake::class);
        $billing->setLicenses([
            $organizationId => new ResolvedLicense($type, RelayLicense::trial()),
        ]);
    }

    /**
     * KYC emails are sent from the system project
     */
    private function seedSystemProjectInfra(): void
    {
        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);
        $instance = $instanceService->tryGetInstance() ?? InstanceFactory::new()->withDefaultDkim()->create();

        /** @var Config $config */
        $config = $this->container->get(Config::class);

        DomainFactory::createOne([
            'project' => $instance->getSystemProject(),
            'domain' => $config->getInstanceDomain(),
            'status' => DomainStatus::ACTIVE,
        ]);
    }

    private function getSavedQueueName(Project $project): ?string
    {
        $this->em->clear();
        $saved = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($saved);
        return $saved->getQueue()?->getFullName();
    }

    public function test_sends_of_trial_organizations_use_the_trial_queue(): void
    {
        InstanceFactory::new()->withDefaultDkim()->create();
        $ip = IpAddressFactory::createOne(['queue' => $this->transactionalTrial]);

        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE,
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'accept@simulator.relay.hyvor.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(200);

        $send = $this->em->getRepository(Send::class)->findOneBy(['project' => $project->getId()]);
        $this->assertNotNull($send);
        $this->assertSame('transactional:trial', $send->getQueueName());
        $this->assertSame($ip->getId(), $send->getIpAddress()?->getId());
    }

    public function test_kyc_approval_moves_projects_to_production_queues(): void
    {
        $this->seedSystemProjectInfra();

        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);
        $project = ProjectFactory::createOne([
            'organization_id' => $kyc->getOrganizationId(),
            'queue' => $this->transactionalTrial,
        ]);
        $this->setLicense($kyc->getOrganizationId(), ResolvedLicenseType::SUBSCRIPTION);

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame($this->transactionalDefault->getFullName(), $this->getSavedQueueName($project));
    }

    public function test_kyc_rejection_moves_projects_to_trial_queues_when_approved_kyc_is_staled(): void
    {
        $this->seedSystemProjectInfra();

        $approved = KycFactory::createOne(['status' => KycStatus::APPROVED]);
        $pending = KycFactory::createOne([
            'organization_id' => $approved->getOrganizationId(),
            'status' => KycStatus::PENDING,
        ]);
        $this->setLicense($approved->getOrganizationId(), ResolvedLicenseType::SUBSCRIPTION);

        $project = ProjectFactory::createOne([
            'organization_id' => $approved->getOrganizationId(),
            'queue' => $this->transactionalDefault,
        ]);

        $response = $this->sudoApi('POST', '/kyc/' . $pending->getId() . '/reject');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame($this->transactionalTrial->getFullName(), $this->getSavedQueueName($project));
    }
}
