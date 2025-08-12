<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\DomainController;
use App\Api\Console\Object\DomainObject;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainObject::class)]
class GetDomainTest extends WebTestCase
{

    public function test_fails_when_domain_not_found(): void
    {
        $project = ProjectFactory::createOne();
        $response = $this->consoleApi(
            $project,
            'GET',
            '/domains/by',
            [
                'id' => 9999,
            ],
            scopes: [Scope::DOMAINS_READ]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Domain not found', $this->getJson()['message']);
    }

    public function test_gets_domain(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            'project' => $project,
            'domain' => 'example.com',
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/domains/by',
            [
                'id' => $domain->getId(),
            ],
            scopes: [Scope::DOMAINS_READ]
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($domain->getId(), $json['id']);
        $this->assertArrayHasKey('domain', $json);
        $this->assertSame('example.com', $json['domain']);
    }

    public function test_by_domain_when_not_found(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'GET',
            '/domains/by',
            data: [
                'domain' => 'example.com',
            ],
            scopes: [Scope::DOMAINS_READ]
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Domain not found', $this->getJson()['message']);
    }

    public function test_get_domain_by_domain(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            'project' => $project,
            'domain' => 'example.com',
        ]);

        $this->consoleApi(
            $project,
            'GET',
            '/domains/by',
            data: [
                'domain' => 'example.com',
            ],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getJson();
        $this->assertEquals($domain->getId(), $response['id']);
        $this->assertEquals($domain->getDomain(), $response['domain']);
    }

}