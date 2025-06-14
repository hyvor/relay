<?php

namespace App\Tests\Api\Console\Email;

use App\Entity\Send;
use App\Entity\Type\SendStatus;
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

    }

}