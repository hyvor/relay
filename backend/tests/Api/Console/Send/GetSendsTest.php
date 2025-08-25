<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendObject;
use App\Entity\Send;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
class GetSendsTest extends WebTestCase
{
    public function test_list_sends_non_empty(): void
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
            '/sends'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(10, $json);
        $send = $json[4];
        $this->assertArrayHasKey('id', $send);
    }

    public function test_list_sends_empty(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(0, $json);
    }

    public function test_list_sends_with_limit_and_offset(): void
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
            '/sends?limit=5&offset=2'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(5, $json);
    }

    #[TestWith([SendRecipientStatus::QUEUED, SendRecipientStatus::ACCEPTED])]
    #[TestWith([SendRecipientStatus::ACCEPTED, SendRecipientStatus::BOUNCED])]
    #[TestWith([SendRecipientStatus::BOUNCED, SendRecipientStatus::QUEUED])]
    public function test_list_sends_with_status_search(SendRecipientStatus $status, SendRecipientStatus $otherStatus): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $sendRecipients = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => $status,
        ]);

        $sendOtherStatus = SendFactory::createOne( [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $sendRecipientsOtherStatus = SendRecipientFactory::createOne([
            'send' => $sendOtherStatus,
            'status' => $otherStatus,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            "/sends?status={$status->value}"
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(1, $json);

        $send = $json[0];
        $this->assertArrayHasKey('id', $send);

        $repository = $this->em->getRepository(Send::class);
        $subscriberDb = $repository->find($send['id']);
        $this->assertInstanceOf(Send::class, $subscriberDb);
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
            '/sends?from_search=thibault'
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

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        SendRecipientFactory::createOne( [
            'send' => $send,
            'address' => 'thibault@hyvor.com'
        ]);

        $sendOther = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'supun@hyvor.com'
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends?to_search=thibault'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(1, $json);
        $sendResponse = $json[0];
        $this->assertArrayHasKey('id', $sendResponse);
        $repository = $this->em->getRepository(Send::class);
        $sendDb = $repository->find($sendResponse['id']);
        $this->assertInstanceOf(Send::class, $sendDb);
        $this->assertSame($send->getRecipients()[0]->getAddress(), $sendDb->getRecipients()[0]->getAddress());
    }
}
