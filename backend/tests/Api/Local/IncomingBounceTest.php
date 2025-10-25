<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Api\Local\Input\DsnInput;
use App\Api\Local\Input\DsnRecipientsInput;
use App\Api\Local\Input\IncomingInput;
use App\Entity\DebugIncomingEmail;
use App\Entity\Suppression;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use App\Entity\Type\SuppressionReason;
use App\Service\DebugIncomingEmail\DebugIncomingEmailService;
use App\Service\IncomingMail\Dto\BounceDto;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\IncomingMailService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
#[CoversClass(IncomingMailService::class)]
#[CoversClass(IncomingInput::class)]
#[CoversClass(DsnInput::class)]
#[CoversClass(DsnRecipientsInput::class)]
#[CoversClass(DebugIncomingEmailService::class)]
#[CoversClass(BounceDto::class)]
#[CoversClass(IncomingBounceEvent::class)]
class IncomingBounceTest extends WebTestCase
{
    public function test_incoming_bounce(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project
        ]);
        SendRecipientFactory::createOne(['send' => $send, 'address' => 'nadil@hyvor.com']);
        SendRecipientFactory::createOne(['send' => $send, 'address' => 'supun@hyvor.com']);

        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'bounce',
                'dsn' => [
                    'ReadableText' => 'This is a test DSN',
                    'Recipients' => [
                        [
                            'EmailAddress' => 'nadil@hyvor.com',
                            'Status' => '5.1.1',
                            'Action' => 'failed',
                        ],
                        [
                            'EmailAddress' => 'supun@hyvor.com',
                            'Status' => '5.1.1',
                            'Action' => 'failed',
                        ],
                    ]
                ],
                'bounce_uuid' => $send->getUuid(),
                'raw_email' => 'This is a raw email content',
                'mail_from' => 'mail.from@example.com',
                'rcpt_to' => 'rcpt.to@example.com'
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);

        $this->assertSame(2, count($suppressions));
        $this->assertSame('nadil@hyvor.com', $suppressions[0]->getEmail());
        $this->assertSame(SuppressionReason::BOUNCE, $suppressions[0]->getReason());
        $this->assertSame('This is a test DSN', $suppressions[0]->getDescription());
        $this->assertSame('supun@hyvor.com', $suppressions[1]->getEmail());
        $this->assertSame(SuppressionReason::BOUNCE, $suppressions[1]->getReason());
        $this->assertSame('This is a test DSN', $suppressions[1]->getDescription());

        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::BOUNCE,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'mail.from@example.com',
            'rcpt_to' => 'rcpt.to@example.com'
        ]);
        $this->assertNotNull($debugIncomingEmail);
        $this->assertSame('This is a raw email content', $debugIncomingEmail->getRawEmail());
        $this->assertNull($debugIncomingEmail->getErrorMessage());
    }

    public function test_incoming_bounce_dsn_missing(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);

        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'bounce',
                'bounce_uuid' => $send->getUuid(),
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com',
                'error' => 'DSN missing',
            ]
        );
        $this->assertSame(200, $response->getStatusCode());
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::BOUNCE,
            'status' => DebugIncomingEmailStatus::FAILED,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
        $this->assertSame('raw', $debugIncomingEmail->getRawEmail());
        $this->assertSame('DSN missing', $debugIncomingEmail->getErrorMessage());
        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);
        $this->assertCount(0, $suppressions);
    }

    public function test_incoming_bounce_no_recipients(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'bounce',
                'dsn' => [
                    'ReadableText' => 'No recipients',
                    'Recipients' => []
                ],
                'bounce_uuid' => $send->getUuid(),
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com'
            ]
        );
        $this->assertSame(200, $response->getStatusCode());
        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);
        $this->assertCount(0, $suppressions);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::BOUNCE,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
    }

    public function test_incoming_bounce_non_failed_action(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'bounce',
                'dsn' => [
                    'ReadableText' => 'Non failed',
                    'Recipients' => [
                        [
                            'EmailAddress' => 'nadil@hyvor.com',
                            'Status' => '5.1.1',
                            'Action' => 'delayed',
                        ]
                    ]
                ],
                'bounce_uuid' => $send->getUuid(),
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com'
            ]
        );
        $this->assertSame(200, $response->getStatusCode());
        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);
        $this->assertCount(0, $suppressions);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::BOUNCE,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
    }

    public function test_incoming_bounce_non_permanent_status(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'bounce',
                'dsn' => [
                    'ReadableText' => 'Non permanent',
                    'Recipients' => [
                        [
                            'EmailAddress' => 'nadil@hyvor.com',
                            'Status' => '4.1.1',
                            'Action' => 'failed',
                        ]
                    ]
                ],
                'bounce_uuid' => $send->getUuid(),
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com'
            ]
        );
        $this->assertSame(200, $response->getStatusCode());
        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);
        $this->assertCount(0, $suppressions);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::BOUNCE,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
    }

    public function test_incoming_bounce_send_not_found(): void
    {
        $project = ProjectFactory::createOne();
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'bounce',
                'dsn' => [
                    'ReadableText' => 'Send not found',
                    'Recipients' => [
                        [
                            'EmailAddress' => 'nadil@hyvor.com',
                            'Status' => '5.1.1',
                            'Action' => 'failed',
                        ]
                    ]
                ],
                'bounce_uuid' => '123e4567-e89b-12d3-a456-426614174000',
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com'
            ]
        );
        $this->assertSame(200, $response->getStatusCode());
        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);
        $this->assertCount(0, $suppressions);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::BOUNCE,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
    }
}
