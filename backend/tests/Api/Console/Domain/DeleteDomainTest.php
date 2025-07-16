<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Controller\DomainController;
use App\Api\Console\Object\DomainObject;
use App\Entity\Domain;
use App\Service\Domain\DomainService;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Util\EventDispatcher\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainService::class)]
#[CoversClass(DomainObject::class)]
class DeleteDomainTest extends WebTestCase
{
    public function test_delete_domain(): void
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
            '/domains/' . $domain->getId(),
        );

        $this->assertResponseStatusCodeSame(200);

        $domainDb = $this->em->getRepository(Domain::class)->find($domainId);
        $this->assertNull($domainDb, 'Domain should be deleted from the database');
        $eventDispatcher->assertDispatched(DomainDeletedEvent::class);
    }

    public function test_delete_non_existent_domain(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'DELETE',
            '/domains/999999', // Assuming this ID does not exist
        );

        $this->assertResponseStatusCodeSame(404);
        $json = $this->getJson();
        $this->assertSame('Entity not found', $json['message']);
    }
}
