<?php

namespace App\Tests\Api\Console\Domain;

use App\Entity\Domain;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;

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
        dd($this->em->getRepository(Domain::class)->findAll());

        $this->assertSame('example.com', $json['domain']);
        $dkimSelector = $json['dkim_selector'];
        $this->assertStringStartsWith('rly', $dkimSelector);
        $this->assertSame($dkimSelector . '._domainkey.example.com', $json['dkim_host']);

        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $json['dkim_txt_value']);
    }

}