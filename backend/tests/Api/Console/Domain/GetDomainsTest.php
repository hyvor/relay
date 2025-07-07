<?php

namespace App\Tests\Api\Console\Domain;

use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;

class GetDomainsTest extends WebTestCase
{
    public function test_get_domains(): void
    {
        $project = ProjectFactory::createOne();

        $otherProject = ProjectFactory::createOne();

        $domains = DomainFactory::createMany(10, [
            'project' => $project,
        ]);

        DomainFactory::createMany(5, [
            'project' => $otherProject,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/domains?limit=5&offset=0',
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertCount(5, $json);
    }

    public function test_get_domains_with_search(): void
    {
        $project = ProjectFactory::createOne();

        $otherProject = ProjectFactory::createOne();

        $domains = DomainFactory::createMany(10, [
            'project' => $project,
        ]);

        $domainToFind = DomainFactory::createOne([
            'project' => $project,
            'domain' => 'thibault.dev',
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/domains?search=thibault',
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertCount(1, $json);
        $this->assertSame('thibault.dev', $json[0]['domain']);
    }
}
