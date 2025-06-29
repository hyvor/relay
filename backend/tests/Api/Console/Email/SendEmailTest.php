<?php

namespace App\Tests\Api\Console\Email;

use App\Entity\Send;
use App\Entity\Type\SendStatus;
use App\Service\Email\Message\EmailSendMessage;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;

class SendEmailTest extends WebTestCase
{

    public function test_queues_mail(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            'project' => $project,
            'domain' => 'hyvor.com'
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends',
            data: [
                'from' => 'supun@hyvor.com',
                'to' => 'somebody@example.com',
                'subject' => 'Test Email',
                'body_text' => 'This is a test email.',
                'body_html' => '<p>This is a test email.</p>',
            ]
        );

        $this->assertResponseStatusCodeSame(200);

        $send = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $send);

        $send = $send[0];
        $this->assertSame(SendStatus::QUEUED, $send->getStatus());
        $this->assertSame('Test Email', $send->getSubject());
    }

}