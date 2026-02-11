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

        $this->assertSame(200, $response->getStatusCode());
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

        $sendAfter = time() + 3600;

        $response = $this->consoleApi(
            $project,
            'POST',
            '/sends/' . $send->getId() . '/retry',
            data: ['send_after' => $sendAfter],
            scopes: [Scope::SENDS_SEND]
        );

        $this->assertSame(200, $response->getStatusCode());

        $this->em->refresh($send->_real());
        $this->assertSame($sendAfter, $send->getSendAfter()->getTimestamp());
    }

}
