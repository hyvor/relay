<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Controller\DomainController;
use App\Api\Console\Object\DomainObject;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainObject::class)]
class GetDomainByDomainTest extends WebTestCase
{

    public function test_when_not_found(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'GET',
            '/domains/domain/not-found.com'
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
            '/domains/domain/example.com'
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getJson();
        $this->assertEquals($domain->getId(), $response['id']);
        $this->assertEquals($domain->getDomain(), $response['domain']);
    }

}