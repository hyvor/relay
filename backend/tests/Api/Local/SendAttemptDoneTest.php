<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Entity\Suppression;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Send\SendService;
use App\Service\SendAttempt\Event\SendAttemptCreatedEvent;
use App\Service\SendAttempt\SendAttemptService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendAttemptRecipientFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendAttemptService::class)]
class SendAttemptDoneTest extends WebTestCase
{

    public function test_dispatches_events(): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);

        $attempt1 = SendAttemptFactory::createOne();
        $attempt2 = SendAttemptFactory::createOne();

        $this->localApi(
            "POST",
            "/send-attempts/done",
            [
                'send_attempt_ids' => [
                    $attempt1->getId(),
                    $attempt2->getId(),
                    3423 // ignored
                ],
            ],
        );

        $this->assertResponseIsSuccessful();

        $eventDispatcher->assertDispatchedCount(SendAttemptCreatedEvent::class, 2);
    }

    public function test_creates_suppression_for_recipient_bounces(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
        ]);

        $recipient1 = SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'one@hyvor.com',
        ]);

        $recipient2 = SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'two@hyvor.com',
        ]);

        $recipient3 = SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'three@example.com',
        ]);

        // other send
        SendRecipientFactory::createOne([
            'address' => 'four@hyvor.com',
        ]);

        $attempt1 = SendAttemptFactory::createOne([
            'send' => $send,
            'domain' => 'hyvor.com',
            'status' => SendAttemptStatus::BOUNCED,
        ]);

        // recipient bounce
        SendAttemptRecipientFactory::createOne([
            'send_attempt' => $attempt1,
            'send_recipient_id' => $recipient1->getId(),
            'recipient_status' => SendRecipientStatus::BOUNCED,
            'smtp_code' => 550,
            'smtp_enhanced_code' => '5.1.1',
            'smtp_message' => 'User unknown',
        ]);

        // infra bounce
        SendAttemptRecipientFactory::createOne([
            'send_attempt' => $attempt1,
            'send_recipient_id' => $recipient2->getId(),
            'recipient_status' => SendRecipientStatus::BOUNCED,
            'smtp_code' => 550,
            'smtp_enhanced_code' => '5.7.1',
            'smtp_message' => 'User unknown',
        ]);

        $this->localApi(
            "POST",
            "/send-attempts/done",
            [
                'send_attempt_ids' => [$attempt1->getId()],
            ],
        );
        $this->assertResponseIsSuccessful();

        $suppressions = $this->em
            ->getRepository(Suppression::class)
            ->findBy(['project' => $project->getId()]);

        $this->assertCount(1, $suppressions);

        $this->assertSame('one@hyvor.com', $suppressions[0]->getEmail());
        $this->assertSame('550 5.1.1 User unknown', $suppressions[0]->getDescription());
    }
}
