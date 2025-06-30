<?php

namespace App\Tests\Api\Console\Email;

use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendObject;
use App\Entity\Send;
use App\Entity\Type\SendStatus;
use App\Service\Email\EmailBuilder;
use App\Service\Email\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
#[CoversClass(EmailBuilder::class)]
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
    #[ // headers not array
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'headers' => 'not an array',
            ],
            "headers",
            "This value should be of type array.",
        ])
    ]
    #[ // headers array keys not strings
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'headers' => [
                    123 => "value",
                    "valid-header" => "value",
                ],
            ],
            "headers",
            "The header key 123 must be a string.",
        ])
    ]
    #[ // headers array value not strings
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'headers' => [
                    "valid-header" => "value",
                    "invalid-value" => 123,
                ],
            ],
            "headers",
            "The header value of invalid-value must be a string.",
        ])
    ]
    #[ // headers not allowed
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'headers' => [
                    "from" => 'some from'
                ],
            ],
            "headers",
            "The header from is not allowed as a custom header.",
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
            'dkim_verified' => true,
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

        $json = $this->getJson();
        $sendId = $json['id'];
        $this->assertIsInt($sendId);
        $messageId = $json['message_id'];
        $this->assertIsString($messageId);

        $send = $this->em->getRepository(Send::class)->findBy([
            'id' => $sendId,
        ]);
        $this->assertCount(1, $send);

        $send = $send[0];
        $this->assertSame(SendStatus::QUEUED, $send->getStatus());
        $this->assertSame("Test Email", $send->getSubject());
        $this->assertSame("This is a test email.", $send->getBodyText());
        $this->assertSame("<p>This is a test email.</p>", $send->getBodyHtml());
        $this->assertSame($messageId, $send->getMessageId());
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

        $raw = $send->getRaw();

        $rawSplit = explode("\r\n\r\n", $raw, 2);
        $rawHeaders = $rawSplit[0];
        $rawBody = $rawSplit[1];

        $fromHeader = $useArrayAddress ? "Supun <supun@hyvor.com>" : "supun@hyvor.com";
        $toHeader =  $useArrayAddress ? "Somebody <somebody@example.com>" : "somebody@example.com";
        $this->assertStringContainsString("From: $fromHeader\r\n", $rawHeaders);
        $this->assertStringContainsString("To: $toHeader\r\n", $rawHeaders);
        $this->assertStringContainsString("Subject: Test Email\r\n", $rawHeaders);
        $this->assertStringContainsString("MIME-Version: 1.0\r\n", $rawHeaders);
        $this->assertStringContainsString("\r\nDate:", $rawHeaders);
        $this->assertStringContainsString("\r\nMessage-ID: <$messageId>", $rawHeaders);

        $this->assertStringContainsString("\r\nDKIM-Signature: v=1; q=dns/txt; a=rsa-sha256;\r\n", $rawHeaders);
        // signed from the FROM domain
        $this->assertStringContainsString("d=hyvor.com;", $rawHeaders);
        // signed from the instance domain
        $this->assertStringContainsString("d=relay.hyvor.localhost;", $rawHeaders);
        $this->assertStringContainsString("\r\nContent-Type: multipart/alternative;", $rawHeaders);
        // custom headers
        $this->assertStringContainsString("X-Custom-Header: Custom Value\r\n", $rawHeaders);
        // X-Mailer header
        $this->assertStringContainsString("X-Mailer: Hyvor Relay v0.0.0\r\n", $rawHeaders);

        $this->assertStringContainsString("\r\nContent-Transfer-Encoding: quoted-printable\r\n", $rawBody);
        $this->assertStringContainsString("This is a test email.", $rawBody);
        $this->assertStringContainsString("<p>This is a test email.</p>", $rawBody);
    }

    public function test_does_not_allow_unregistered_domain(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertSame(
            "Domain hyvor.com is not registered for this project",
            $json['message']
        );

    }

    public function test_does_not_allow_from_unverified_domain(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'dkim_verified' => false,
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertSame(
            "Domain hyvor.com is not verified",
            $json['message']
        );

    }

}
