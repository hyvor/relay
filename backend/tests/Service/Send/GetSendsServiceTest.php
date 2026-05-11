<?php

namespace App\Tests\Service\Send;

use App\Service\Send\SendService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SendService::class)]
class GetSendsServiceTest extends KernelTestCase
{
    private SendService $sendService;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var SendService $sendService */
        $sendService = $this->container->get(SendService::class);
        $this->sendService = $sendService;
    }

    public function test_get_sends_with_null_project_returns_all_projects(): void
    {
        $projectA = ProjectFactory::createOne();
        $projectB = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(3, [
            'project' => $projectA,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendFactory::createMany(2, [
            'project' => $projectB,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $result = $this->sendService->getSends(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            50,
            null
        );

        $this->assertCount(5, $result);
    }

    public function test_get_sends_with_project_scopes_to_that_project(): void
    {
        $projectA = ProjectFactory::createOne();
        $projectB = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(3, [
            'project' => $projectA,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendFactory::createMany(2, [
            'project' => $projectB,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $result = $this->sendService->getSends(
            $projectA->_real(),
            null,
            null,
            null,
            null,
            null,
            null,
            50,
            null
        );

        $this->assertCount(3, $result);
    }
}
