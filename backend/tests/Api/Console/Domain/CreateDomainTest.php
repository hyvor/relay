<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Controller\DomainController;
use App\Api\Console\Object\DomainObject;
use App\Service\App\Validator\DkimPrivateKeyValidator;
use App\Service\Domain\Dkim;
use App\Service\Domain\DomainService;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainService::class)]
#[CoversClass(DomainObject::class)]
#[CoversClass(DomainCreatedEvent::class)]
#[CoversClass(DkimPrivateKeyValidator::class)]
class CreateDomainTest extends WebTestCase
{

    public function test_fails_when_domain_already_exists(): void
    {
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

        $this->getEd()->assertNotDispatched(DomainCreatedEvent::class);
    }

    public function test_creates_domain(): void
    {
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

        $this->getEd()->assertDispatched(DomainCreatedEvent::class);
        $firstEvent = $this->getEd()->getFirstEvent(DomainCreatedEvent::class);
        $this->assertSame(
            $json['id'],
            $firstEvent->domain->getId()
        );
    }

    public function test_creates_domain_with_custom_selector(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
                'dkim_selector' => 'mysel',
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame('example.com', $json['domain']);
        $this->assertSame('mysel', $json['dkim_selector']);
        $this->assertSame('mysel._domainkey.example.com', $json['dkim_host']);
    }

    public function test_creates_domain_with_custom_private_key(): void
    {
        $project = ProjectFactory::createOne();

        ['private' => $privateKey, 'public' => $publicKey] = Dkim::generateDkimKeys();

        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
                'dkim_private_key' => $privateKey,
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame('example.com', $json['domain']);
        $this->assertSame($publicKey, $json['dkim_public_key']);
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $json['dkim_txt_value']);
    }

    public function test_fails_with_invalid_selector(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
                'dkim_selector' => 'invalid selector!',
            ],
        );

        $this->assertResponseFailed(422, 'DKIM selector must be a valid DNS label');
    }

    public function test_fails_with_invalid_private_key(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
                'dkim_private_key' => 'invalid private key!',
            ],
        );

        $this->assertResponseFailed(422, 'The provided DKIM private key is invalid or poorly formatted');
    }

    public function test_checks_private_key_bits(): void
    {

        $project = ProjectFactory::createOne();

        ['private' => $privateKey] = Dkim::generateDkimKeys(bits: 512);

        $this->consoleApi(
            $project,
            'POST',
            '/domains',
            [
                'domain' => 'example.com',
                'dkim_private_key' => $privateKey,
            ],
        );

        $this->assertResponseFailed(422, 'The private key is too weak (512 bits)');

    }

}
