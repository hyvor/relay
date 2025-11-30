<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Controller\DomainController;
use App\Api\Console\Object\DomainObject;
use App\Service\Domain\DomainService;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainService::class)]
#[CoversClass(DomainObject::class)]
#[CoversClass(DomainCreatedEvent::class)]
class CreateDomainTest extends WebTestCase
{

    public function test_fails_when_domain_already_exists(): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            'project' => $project,
            'domain' => 'example.com',
        ]);


        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
            ],
        );


        $this->assertResponseStatusCodeSame(400);
        $json = $this->getJson();
        $this->assertSame('Domain already exists', $json['message']);

        $eventDispatcher->assertNotDispatched(DomainCreatedEvent::class);
    }

    public function test_creates_domain(): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);

        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();

        $this->assertSame('example.com', $json['domain']);
        $dkimSelector = $json['dkim_selector'];
        $this->assertIsString($dkimSelector);

        $this->assertStringStartsWith('rly', $dkimSelector);
        $this->assertSame($dkimSelector . '._domainkey.example.com', $json['dkim_host']);

        $dkimTxtValue = $json['dkim_txt_value'];
        $this->assertIsString($dkimTxtValue);
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $dkimTxtValue);

        $eventDispatcher->assertDispatched(DomainCreatedEvent::class);
        $firstEvent = $eventDispatcher->getFirstEvent(DomainCreatedEvent::class);
        $this->assertSame(
            $json['id'],
            $firstEvent->domain->getId()
        );
    }

}
