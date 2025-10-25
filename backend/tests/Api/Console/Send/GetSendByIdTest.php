<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendAttemptRecipientObject;
use App\Api\Console\Object\SendFeedbackObject;
use App\Api\Console\Object\SendObject;
use App\Api\Console\Object\SendRecipientObject;
use App\Service\SendAttempt\SendAttemptService;
use App\Service\SendFeedback\SendFeedbackService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendAttemptRecipientFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendFeedbackFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SendController::class)]
#[CoversClass(SendObject::class)]
#[CoversClass(SendRecipientObject::class)]
#[CoversClass(SendAttemptRecipientObject::class)]
#[CoversClass(SendFeedbackObject::class)]
#[CoversClass(SendAttemptObject::class)]
#[CoversClass(SendAttemptService::class)]
#[CoversClass(SendFeedbackService::class)]
class GetSendByIdTest extends WebTestCase
{

    public function test_fails_when_not_found(): void
    {
        $project = ProjectFactory::createOne();
        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/123',
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(404, $response->getStatusCode());
        $json = $this->getJson();
        $this->assertSame('Entity not found', $json['message']);
    }

    public function test_get_specific_email(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne(
            [
                'project' => $project,
                'domain' => $domain,
                'queue' => $queue,
            ]
        );

        $recipient = SendRecipientFactory::createOne(['send' => $send]);

        $attempt = SendAttemptFactory::createOne([
            'send' => $send,
        ]);

        SendAttemptRecipientFactory::createOne([
            'send_attempt' => $attempt,
            'send_recipient_id' => $recipient->getId(),
        ]);

        SendFeedbackFactory::createOne([
            'sendRecipient' => $recipient
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/' . $send->getId(),
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($send->getId(), $json['id']);

        $attempts = $json['attempts'];
        $this->assertIsArray($attempts);
        $this->assertCount(1, $attempts);
    }

}