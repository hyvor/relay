<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Api\Local\Input\ArfInput;
use App\Api\Local\Input\IncomingInput;
use App\Entity\DebugIncomingEmail;
use App\Entity\SendFeedback;
use App\Entity\Suppression;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use App\Entity\Type\SendFeedbackType;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SuppressionReason;
use App\Service\IncomingMail\Dto\ComplaintDto;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\Event\IncomingComplaintEvent;
use App\Service\IncomingMail\IncomingMailService;
use App\Service\SendFeedback\SendFeedbackService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
#[CoversClass(IncomingMailService::class)]
#[CoversClass(IncomingInput::class)]
#[CoversClass(ArfInput::class)]
#[CoversClass(ComplaintDto::class)]
#[CoversClass(IncomingComplaintEvent::class)]
#[CoversClass(SendFeedbackService::class)]
class IncomingComplaintTest extends WebTestCase
{
    public function test_incoming_complaint(): void
    {
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'ip_address' => $ipAddress,
        ]);
        $recipient = SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'spammer@example.net'
        ]);

        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'fbl',
                'arf' => [
                    'ReadableText' => 'This is a test ARF',
                    'FeedbackType' => 'abuse',
                    'UserAgent' => 'SomeUserAgent/1.0',
                    'OriginalMailFrom' => 'user@example.net',
                    'OriginalRcptTo' => 'spammer@example.net',
                    'MessageId' => "{$send->getUuid()}@example.net"
                ],
                'raw_email' => 'This is a raw email content',
                'mail_from' => 'mail.from@example.com',
                'rcpt_to' => 'rcpt.to@example.com'
            ]
        );

        $this->assertResponseStatusCodeSame(200, $response);

        $suppression = $this->em->getRepository(Suppression::class)->findOneBy([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT
        ]);

        $this->assertNotNull($suppression);
        $this->assertEquals('spammer@example.net', $suppression->getEmail());
        $this->assertEquals('This is a test ARF', $suppression->getDescription());

        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::COMPLAINT,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'mail.from@example.com',
            'rcpt_to' => 'rcpt.to@example.com'
        ]);
        $this->assertNotNull($debugIncomingEmail);
        $this->assertSame('This is a raw email content', $debugIncomingEmail->getRawEmail());
        $this->assertNull($debugIncomingEmail->getErrorMessage());

        $this->assertSame(SendRecipientStatus::COMPLAINED, $recipient->getStatus());

        $feedback = $this->em->getRepository(SendFeedback::class)->findOneBy(['send' => $send]);
        $this->assertNotNull($feedback);
        $this->assertSame(SendFeedbackType::COMPLAINT, $feedback->getType());
        $this->assertSame($project->getId(), $feedback->getProject()->getId());
        $this->assertSame($recipient->getId(), $feedback->getSendRecipient()?->getId());
        $this->assertSame($ipAddress->getId(), $feedback->getIpAddress()?->getId());
        $this->assertSame('abuse', $feedback->getDetail());
        $this->assertSame($debugIncomingEmail->getId(), $feedback->getDebugIncomingEmail()->getId());
    }

    public function test_incoming_complaint_redacted_recipient(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $recipient = SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'spammer@example.net'
        ]);

        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'fbl',
                'arf' => [
                    'ReadableText' => 'Redacted recipient',
                    'FeedbackType' => 'abuse',
                    'UserAgent' => 'UA',
                    'OriginalMailFrom' => 'user@example.net',
                    'OriginalRcptTo' => '',
                    'MessageId' => "{$send->getUuid()}@example.net"
                ],
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com',
            ]
        );
        $this->assertResponseStatusCodeSame(200, $response);

        $feedback = $this->em->getRepository(SendFeedback::class)->findOneBy(['send' => $send]);
        $this->assertNotNull($feedback);
        $this->assertSame(SendFeedbackType::COMPLAINT, $feedback->getType());
        $this->assertNull($feedback->getSendRecipient());

        $suppression = $this->em->getRepository(Suppression::class)->findOneBy([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT
        ]);
        $this->assertNull($suppression);
        $this->assertNotSame(SendRecipientStatus::COMPLAINED, $recipient->getStatus());
    }

    public function test_incoming_complaint_arf_missing_error_provided(): void
    {
        $project = ProjectFactory::createOne();
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'fbl',
                'error' => 'ARF missing',
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com',
            ]
        );
        $this->assertResponseStatusCodeSame(200, $response);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::COMPLAINT,
            'status' => DebugIncomingEmailStatus::FAILED,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
        $this->assertSame('raw', $debugIncomingEmail->getRawEmail());
        $this->assertSame('ARF missing', $debugIncomingEmail->getErrorMessage());
        $suppression = $this->em->getRepository(Suppression::class)->findOneBy([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT
        ]);
        $this->assertNull($suppression);
    }

    public function test_incoming_complaint_invalid_message_id(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'fbl',
                'arf' => [
                    'ReadableText' => 'Invalid MessageId',
                    'FeedbackType' => 'abuse',
                    'UserAgent' => 'UA',
                    'OriginalMailFrom' => 'user@example.net',
                    'OriginalRcptTo' => 'spammer@example.net',
                    'MessageId' => 'invalid-message-id'
                ],
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com',
            ]
        );
        $this->assertResponseStatusCodeSame(200, $response);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::COMPLAINT,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
        $suppression = $this->em->getRepository(Suppression::class)->findOneBy([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT
        ]);
        $this->assertNull($suppression);
    }

    public function test_incoming_complaint_send_not_found(): void
    {
        $project = ProjectFactory::createOne();
        $response = $this->localApi(
            'POST',
            '/incoming',
            [
                'type' => 'fbl',
                'arf' => [
                    'ReadableText' => 'Send not found',
                    'FeedbackType' => 'abuse',
                    'UserAgent' => 'UA',
                    'OriginalMailFrom' => 'user@example.net',
                    'OriginalRcptTo' => 'spammer@example.net',
                    'MessageId' => '123e4567-e89b-12d3-a456-426614174000@example.net'
                ],
                'raw_email' => 'raw',
                'mail_from' => 'from@example.com',
                'rcpt_to' => 'to@example.com',
            ]
        );
        $this->assertResponseStatusCodeSame(200, $response);
        $debugIncomingEmail = $this->em->getRepository(DebugIncomingEmail::class)->findOneBy([
            'type' => DebugIncomingEmailType::COMPLAINT,
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'mail_from' => 'from@example.com',
            'rcpt_to' => 'to@example.com',
        ]);
        $this->assertNotNull($debugIncomingEmail);
        $suppression = $this->em->getRepository(Suppression::class)->findOneBy([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT
        ]);
        $this->assertNull($suppression);
    }
}
