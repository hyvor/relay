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
    #[ // from empty
        TestWith([
            [
                "from" => "",
                "to" => "somebody@example.com",
                "body_text" => "test",
            ],
            "from",
            "This value should not be blank.",
        ])
    ]
    #[ // from invalid email
        TestWith([
            [
                "from" => "invalid email",
                "to" => "somebody@example.com",
                "body_text" => "test",
            ],
            "from",
            "This value is not a valid email address.",
        ])
    ]
    #[ // from invalid email - array
        TestWith([
            [
                "from" => [
                    'email' => 'invalid email',
                    'name' => 'Invalid Name',
                ],
                "to" => "somebody@example.com",
                "body_text" => "test",
            ],
            "from",
            "This value is not a valid email address.",
        ])
    ]
    #[ // to empty
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "",
                "body_text" => "test",
            ],
            "to",
            "This value should not be blank.",
        ])
    ]
    #[ // to invalid email
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "invalid email",
                "body_text" => "test",
            ],
            "to",
            "This value is not a valid email address.",
        ])
    ]
    #[ // to invalid email - array
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => [
                    'email' => 'invalid email',
                    'name' => 'Invalid Name',
                ],
                "body_text" => "test",
            ],
            "to",
            "This value is not a valid email address.",
        ])
    ]
    #[ // body_text empty
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => null,
                "body_html" => null,
            ],
            "body_text",
            "body_text must not be blank if body_html is null",
        ])
    ]
    #[ // body_html empty
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_html" => null,
                "body_text" => null,
            ],
            "body_html",
            "body_html must not be blank if body_text is null",
        ])
    ]
    public function test_validation(
        array $data,
        string $property,
        string $violationMessage
    ): void {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
        ]);

        $this->consoleApi($project, "POST", "/sends", data: $data);

        $this->assertResponseStatusCodeSame(422);

        $json = $this->getJson();
        $message = $json["message"];
        $this->assertIsString($message);
        $this->assertStringContainsString("Validation failed", $message);

        $this->assertHasViolation($property, $violationMessage);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_queues_mail(bool $useArrayAddress): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
        ]);

        $fromAddress = "supun@hyvor.com";
        $toAddress = "somebody@example.com";

        $this->consoleApi(
            $project,
            "POST",
            "/sends",
            data: [
                "from" => $useArrayAddress ? [
                    'email' => $fromAddress,
                    'name' => 'Supun',
                ] : $fromAddress,
                "to" => $useArrayAddress ? [
                    'email' => $toAddress,
                    'name' => 'Somebody',
                ] : $toAddress,
                "subject" => "Test Email",
                "body_text" => "This is a test email.",
                "body_html" => "<p>This is a test email.</p>",
                "headers" => [
                    "X-Custom-Header" => "Custom Value",
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(200);

        $send = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $send);

        $send = $send[0];
        $this->assertSame(SendStatus::QUEUED, $send->getStatus());
        $this->assertSame("Test Email", $send->getSubject());
        $this->assertSame("This is a test email.", $send->getBodyText());
        $this->assertSame("<p>This is a test email.</p>", $send->getBodyHtml());
        $this->assertSame($fromAddress, $send->getFromAddress());
        $this->assertSame($toAddress, $send->getToAddress());

        if ($useArrayAddress) {
            $this->assertSame("Supun", $send->getFromName());
            $this->assertSame("Somebody", $send->getToName());
        } else {
            $this->assertEmpty($send->getFromName());
            $this->assertEmpty($send->getToName());
        }

        $this->assertSame(
            [
                "X-Custom-Header" => "Custom Value",
            ],
            $send->getHeaders()
        );
    }
}
