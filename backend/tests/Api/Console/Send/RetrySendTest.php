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
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
class RetrySendTest extends WebTestCase
{

    use ClockSensitiveTrait;

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

        $mockClock = $this->mockTime('2024-01-01T12:00:00Z');
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

    public function test_retry_specific_recipients_only(): void
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
            'try_count' => 5,
        ]);

        $recipient2 = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
            'try_count' => 3,
        ]);

        $recipient3 = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            data: ['recipient_ids' => [$recipient1->getId()]],
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame(1, $json['retried_recipients']);

        $this->em->refresh($recipient1->_real());
        $this->em->refresh($recipient2->_real());
        $this->em->refresh($recipient3->_real());

        $this->assertSame(SendRecipientStatus::QUEUED, $recipient1->getStatus());
        $this->assertSame(0, $recipient1->getTryCount());

        // recipient2 is failed but was not in the list — should remain failed
        $this->assertSame(SendRecipientStatus::FAILED, $recipient2->getStatus());
        $this->assertSame(3, $recipient2->getTryCount());

        // recipient3 is accepted — should remain accepted
        $this->assertSame(SendRecipientStatus::ACCEPTED, $recipient3->getStatus());
    }

    public function test_retry_with_invalid_recipient_ids_returns_400(): void
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

        $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            data: ['recipient_ids' => [99999]],
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_try_now_on_queued_send(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $mockClock = $this->mockTime('2024-01-01T12:00:00Z');

        $futureTime = $mockClock->now()->modify('+2 hours');

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'queued' => true,
            'send_after' => $futureTime,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::QUEUED,
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertResponseStatusCodeSame(200);

        $this->em->refresh($send->_real());
        $this->assertSame($mockClock->now()->getTimestamp(), $send->getSendAfter()->getTimestamp());
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

        $mockClock = $this->mockTime('2024-01-01T12:00:00Z');

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
