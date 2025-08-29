<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Controller\DomainController;
use App\Api\Console\Input\Domain\DomainIdOrDomainInput;
use App\Api\Console\Object\DomainObject;
use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\DomainService;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainIdOrDomainInput::class)]
#[CoversClass(DomainService::class)]
#[CoversClass(DomainObject::class)]
class DeleteDomainTest extends WebTestCase
{

    public function test_when_both_id_and_domain_are_null(): void
    {
        $project = ProjectFactory::createOne();
        $this->consoleApi(
            $project,
            'DELETE',
            '/domains',
        );

        $this->assertResponseStatusCodeSame(422);
        $this->assertViolationCount(2);
        $this->assertHasViolation('id', 'Either id or domain must be provided.');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_delete_domain(bool $useDomain): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne(
            [
                'project' => $project,
                'domain' => 'example.com',
            ]
        );

        $domainId = $domain->getId();

        $this->consoleApi(
            $project,
            'DELETE',
            '/domains',
            $useDomain ? ['domain' => 'example.com'] : ['id' => $domainId]
        );

        $this->assertResponseStatusCodeSame(200);

        $domainDb = $this->em->getRepository(Domain::class)->find($domainId);
        $this->assertNull($domainDb);
        $eventDispatcher->assertDispatched(DomainDeletedEvent::class);
    }

    public function test_delete_non_existent_domain(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'DELETE',
            '/domains',
            [
                'id' => 999999
            ]
        );

        $this->assertResponseStatusCodeSame(400);
        $json = $this->getJson();
        $this->assertSame('Domain not found', $json['message']);
    }

    public function test_when_domain_does_not_belong_to_project(): void
    {
        $project = ProjectFactory::createOne();
        $otherProject = ProjectFactory::createOne();

        $domain = DomainFactory::createOne(
            [
                'project' => $otherProject,
                'domain' => 'example.com',
            ]
        );

        $this->consoleApi(
            $project,
            'DELETE',
            '/domains',
            [
                'id' => $domain->getId()
            ]
        );

        $this->assertResponseStatusCodeSame(400);
        $json = $this->getJson();
        $this->assertSame('Domain does not belong to the project', $json['message']);
    }

    public function test_when_domain_is_suspended(): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne(
            [
                'project' => $project,
                'domain' => 'example.com',
                'status' => DomainStatus::SUSPENDED
            ]
        );

        $domainId = $domain->getId();

        $this->consoleApi(
            $project,
            'DELETE',
            '/domains',
            [
                'domain' => 'example.com'
            ]
        );

        $this->assertResponseStatusCodeSame(400);
        $json = $this->getJson();
        $this->assertSame('Domain deletion failed: Suspended domains can not be deleted.', $json['message']);

        $domainDb = $this->em->getRepository(Domain::class)->find($domainId);
        $this->assertNotNull($domainDb);
    }
}
