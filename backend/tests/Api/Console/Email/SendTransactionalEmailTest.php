<?php

namespace App\Tests\Api\Console\Email;

use App\Entity\Send;
use App\Entity\Type\SendStatus;
use App\Service\Email\Message\EmailSendMessage;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;

class SendTransactionalEmailTest extends WebTestCase
{

    public function test_queues_mail(): void
    {
        $project = ProjectFactory::createOne();
        DomainFactory::createOne([
            'domain' => 'hyvor.com'
        ]);

        QueueFactory::createTransactional();

        $this->consoleApi(
            $project,
            'POST',
            '/email/transactional',
            data: [
                'from' => 'supun@hyvor.com',
                'to' => 'somebody@example.com',
                'subject' => 'Test Email',
                'body_html' => '<p>This is a test email.</p>',
                'body_text' => 'This is a test email.'
            ]
        );

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();

        $send = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $send);

        $send = $send[0];
        $this->assertSame(SendStatus::QUEUED, $send->getStatus());
        $this->assertSame('Test Email', $send->getSubject());

        $messages = $this->transport('email')->queue()->messages();
        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertInstanceOf(EmailSendMessage::class, $message);

        $this->assertSame($send->getId(), $message->sendId);
        $this->assertStringContainsString('From: supun@hyvor.com', $message->rawEmail);
        $this->assertStringContainsString('To: somebody@example.com', $message->rawEmail);
    }

}