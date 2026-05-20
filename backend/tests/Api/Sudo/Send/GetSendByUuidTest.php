<?php

namespace App\Tests\Api\Sudo\Send;

use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendAttemptRecipientObject;
use App\Api\Console\Object\SendFeedbackObject;
use App\Api\Console\Object\SendObject;
use App\Api\Console\Object\SendRecipientObject;
use App\Api\Sudo\Controller\SendController;
use App\Service\Send\SendService;
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
use Symfony\Component\Uid\Uuid;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
#[CoversClass(SendAttemptObject::class)]
#[CoversClass(SendAttemptRecipientObject::class)]
#[CoversClass(SendFeedbackObject::class)]
#[CoversClass(SendRecipientObject::class)]
class GetSendByUuidTest extends WebTestCase
{
    public function test_returns_send_with_full_detail(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $recipient = SendRecipientFactory::createOne([
            'send' => $send,
        ]);

        $attempt = SendAttemptFactory::createOne([
            'send' => $send,
        ]);

        SendAttemptRecipientFactory::createOne([
            'send_attempt' => $attempt,
            'send_recipient_id' => $recipient->getId(),
        ]);

        SendFeedbackFactory::createOne([
            'sendRecipient' => $recipient,
        ]);

        $response = $this->sudoApi('GET', '/sends/uuid/' . $send->getUuid());

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        $this->assertSame($send->getId(), $json['id']);
        $this->assertSame($send->getUuid(), $json['uuid']);

        $this->assertArrayHasKey('body_html', $json);
        $this->assertArrayHasKey('body_text', $json);
        $this->assertArrayHasKey('raw', $json);
        $this->assertArrayHasKey('headers', $json);
        $this->assertArrayHasKey('size_bytes', $json);
        $this->assertArrayHasKey('send_after', $json);

        $this->assertIsArray($json['attempts']);
        $this->assertCount(1, $json['attempts']);
        /** @var array<string, mixed> $firstAttempt */
        $firstAttempt = $json['attempts'][0];
        $this->assertIsArray($firstAttempt['recipients']);
        $this->assertCount(1, $firstAttempt['recipients']);
        /** @var array<string, mixed> $attemptRecipient */
        $attemptRecipient = $firstAttempt['recipients'][0];
        $this->assertSame($recipient->getId(), $attemptRecipient['recipient_id']);

        $this->assertIsArray($json['feedback']);
        $this->assertCount(1, $json['feedback']);

        /** @var array<string, mixed> $jsonProject */
        $jsonProject = $json['project'];
        $this->assertSame($project->getId(), $jsonProject['id']);
    }

    public function test_returns_404_when_uuid_unknown(): void
    {
        $uuid = (string) Uuid::v4();
        $response = $this->sudoApi('GET', '/sends/uuid/' . $uuid);

        $this->assertSame(404, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame("Send with UUID $uuid not found", $json['message']);
    }

    public function test_returns_send_across_any_project(): void
    {
        ProjectFactory::createOne();
        $owningProject = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'project' => $owningProject,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $response = $this->sudoApi('GET', '/sends/uuid/' . $send->getUuid());

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<string, mixed> $jsonProject */
        $jsonProject = $json['project'];
        $this->assertSame($owningProject->getId(), $jsonProject['id']);
    }

    public function test_fails_when_not_sudo(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
        ]);

        $this->sudoApi(
            'GET',
            '/sends/uuid/' . $send->getUuid(),
            createSudoUser: false
        );
        $this->assertResponseStatusCodeSame(403);
    }
}
