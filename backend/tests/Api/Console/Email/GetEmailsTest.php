<?php

namespace App\Tests\Api\Console\Email;

use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendObject;
use App\Entity\Send;
use App\Entity\Type\SendStatus;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(SendController::class)]
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

    #[TestWith([SendStatus::QUEUED, SendStatus::ACCEPTED])]
    #[TestWith([SendStatus::ACCEPTED, SendStatus::BOUNCED])]
    #[TestWith([SendStatus::BOUNCED, SendStatus::QUEUED])]
    public function test_list_emails_with_status_search(SendStatus $status, SendStatus $otherStatus): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        $sends = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'status' => $status,
        ]);

        $sendsOtherStatus = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'status' => $otherStatus,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            "/emails?status={$status->value}"
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(10, $json);

        $send = $json[4];
        $this->assertArrayHasKey('id', $send);

        $repository = $this->em->getRepository(Send::class);
        $subscriberDb = $repository->find($send['id']);
        $this->assertInstanceOf(Send::class, $subscriberDb);
        $this->assertSame($sends[4]->getFromAddress(), $subscriberDb->getFromAddress());
    }

    public function test_list_email_with_from_search(): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        $sends = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'fromAddress' => 'thibault@hyvor.com'
        ]);

        $sendsOther = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'fromAddress' => 'supun@hyvor.com'
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails?from_search=thibault'
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
        $this->assertSame($sends[4]->getFromAddress(), $sendDb->getFromAddress());
    }

    public function test_list_email_with_to_search(): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        $sends = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'toAddress' => 'thibault@hyvor.com',
        ]);

        $sendsOther = SendFactory::createMany(10, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'toAddress' => 'supun@hyvor.com'
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails?to_search=thibault'
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
        $this->assertSame($sends[4]->getToAddress(), $sendDb->getToAddress());
    }
}