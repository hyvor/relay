<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\SendController;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
class RetrySendTest extends WebTestCase
{

    public function test_retry_failed_recipients(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'queued' => false,
        ]);

        $recipient1 = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
            'try_count' => 7,
        ]);

        $recipient2 = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
            'try_count' => 7,
        ]);

        $response = $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame(2, $json['retried_recipients']);

        $this->em->refresh($recipient1->_real());
        $this->em->refresh($recipient2->_real());
        $this->assertSame(SendRecipientStatus::QUEUED, $recipient1->getStatus());
        $this->assertSame(0, $recipient1->getTryCount());
        $this->assertSame(SendRecipientStatus::QUEUED, $recipient2->getStatus());
        $this->assertSame(0, $recipient2->getTryCount());

        $this->em->refresh($send->_real());
        $this->assertTrue($send->getQueued());
    }

    public function test_retry_with_send_after(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'queued' => false,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
        ]);

        $mockClock = new MockClock('2024-01-01T12:00:00Z');
        Clock::set($mockClock);

        $sendAfter = $mockClock->now()->getTimestamp() + 3600;

        $response = $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            data: ['send_after' => $sendAfter],
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(200);

        $this->em->refresh($send->_real());
        $this->assertSame($sendAfter, $send->getSendAfter()->getTimestamp());
    }

    public function test_retry_fails_when_send_is_already_queued(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'queued' => true,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_retry_fails_when_no_failed_recipients(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'queued' => false,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_retry_fails_when_send_after_is_in_the_past(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'queued' => false,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
        ]);

        $mockClock = new MockClock('2024-01-01T12:00:00Z');
        Clock::set($mockClock);

        $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            data: ['send_after' => $mockClock->now()->getTimestamp() - 3600],
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(400);
    }

}
