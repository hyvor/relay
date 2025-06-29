<?php

namespace App\Tests\Api\Console\Email;

use App\Entity\Send;
use App\Entity\Type\SendStatus;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\TestWith;

class SendEmailTest extends WebTestCase
{

    /**
     * @param array<string, mixed> $data
     */
    #[TestWith([
        [
            'from' => 'invalid email',
            'to' => 'somebody@example.com',
            'body_text' => 'test',
        ],
        'from',
        'This value is not a valid email address.'
    ])]
    public function test_validation(array $data, string $property, string $violationMessage): void
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
            data: $data
        );

        $this->assertResponseStatusCodeSame(422);

        $json = $this->getJson();
        $message = $json['message'];
        $this->assertIsString($message);
        $this->assertStringContainsString('Validation failed', $message);

        $this->assertHasViolation($property, $violationMessage);

    }

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