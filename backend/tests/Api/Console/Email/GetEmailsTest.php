<?php

namespace App\Tests\Api\Console\Email;

use App\Api\Console\Controller\EmailController;
use App\Api\Console\Object\SendObject;
use App\Entity\Send;
use App\Service\Email\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EmailController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
class GetEmailsTest extends WebTestCase
{
    public function test_list_emails_non_empty(): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        $sends = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(10, $json);
        $send = $json[4];
        $this->assertArrayHasKey('id', $send);

        $repository = $this->em->getRepository(Send::class);
        $sendDb = $repository->find($send['id']);
        $this->assertInstanceOf(Send::class, $sendDb);
        $this->assertSame($sends[4]->getId(), $sendDb->getId());
    }

    public function test_list_emails_empty(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(0, $json);
    }

    public function test_list_emails_with_limit_and_offset(): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails?limit=5&offset=2'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(5, $json);
    }
}