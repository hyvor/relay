<?php

namespace App\Tests\Api\Sudo\Send;

use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendAttemptRecipientObject;
use App\Api\Console\Object\SendFeedbackObject;
use App\Api\Console\Object\SendObject;
use App\Api\Console\Object\SendRecipientObject;
use App\Api\Sudo\Controller\SendController;
use App\Api\Sudo\Object\SendProjectSummaryObject;
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
#[CoversClass(SendProjectSummaryObject::class)]
class GetSendByUuidTest extends WebTestCase
{
    public function test_returns_send_with_full_detail(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $sendEntity = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $recipient = SendRecipientFactory::createOne([
            'send' => $sendEntity,
        ]);

        $attempt = SendAttemptFactory::createOne([
            'send' => $sendEntity,
        ]);

        SendAttemptRecipientFactory::createOne([
            'send_attempt' => $attempt,
            'send_recipient_id' => $recipient->getId(),
        ]);

        SendFeedbackFactory::createOne([
            'sendRecipient' => $recipient,
        ]);

        $response = $this->sudoApi('GET', '/sends/uuid/' . $sendEntity->getUuid());

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        /** @var array<string, mixed> $jsonSend */
        $jsonSend = $json['send'];
        /** @var array<string, mixed> $jsonProject */
        $jsonProject = $json['project'];

        $this->assertSame($sendEntity->getId(), $jsonSend['id']);
        $this->assertSame($sendEntity->getUuid(), $jsonSend['uuid']);

        $this->assertArrayHasKey('body_html', $jsonSend);
        $this->assertArrayHasKey('body_text', $jsonSend);
        $this->assertArrayHasKey('raw', $jsonSend);
        $this->assertArrayHasKey('headers', $jsonSend);
        $this->assertArrayHasKey('size_bytes', $jsonSend);
        $this->assertArrayHasKey('send_after', $jsonSend);

        $this->assertIsArray($jsonSend['attempts']);
        $this->assertCount(1, $jsonSend['attempts']);
        /** @var array<string, mixed> $firstAttempt */
        $firstAttempt = $jsonSend['attempts'][0];
        $this->assertIsArray($firstAttempt['recipients']);
        $this->assertCount(1, $firstAttempt['recipients']);
        /** @var array<string, mixed> $attemptRecipient */
        $attemptRecipient = $firstAttempt['recipients'][0];
        $this->assertSame($recipient->getId(), $attemptRecipient['recipient_id']);

        $this->assertIsArray($jsonSend['feedback']);
        $this->assertCount(1, $jsonSend['feedback']);

        $this->assertSame($project->getId(), $jsonProject['id']);
        $this->assertSame($project->getName(), $jsonProject['name']);
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
        $this->assertArrayHasKey('send', $json);
        /** @var array<string, mixed> $jsonSend */
        $jsonSend = $json['send'];
        $this->assertArrayNotHasKey('project_id', $jsonSend);
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
