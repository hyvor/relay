<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\SendController;
use App\Api\Console\Input\SendEmail\SendEmailInput;
use App\Api\Console\Input\SendEmail\UnableToDecodeAttachmentBase64Exception;
use App\Api\Console\Object\SendObject;
use App\Entity\Send;
use App\Entity\Type\DomainStatus;
use App\Entity\Type\ProjectSendType;
use App\Entity\Type\SendRecipientType;
use App\Entity\Type\SendStatus;
use App\Service\Send\EmailBuilder;
use App\Service\Send\SendService;
use App\Service\Suppression\SuppressionService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SuppressionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendEmailInput::class)]
#[CoversClass(SendObject::class)]
#[CoversClass(EmailBuilder::class)]
#[CoversClass(SuppressionService::class)]
#[CoversClass(UnableToDecodeAttachmentBase64Exception::class)]
class SendEmailTest extends WebTestCase
{

    public function test_scope(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            "POST",
            "/sends",
            data: [
                'from' => 'test@hyvor.com',
                'to' => 'test@example.com',
                'body_text' => 'Test email',
            ],
            scopes: [Scope::SENDS_READ] // Missing sends.send
        );

        $this->assertResponseStatusCodeSame(403);

        $json = $this->getJson();
        $this->assertSame(
            "You do not have the required scope 'sends.send' to access this resource.",
            $json['message']
        );
    }

    protected function getHtmlBodyTooLargeData(): mixed
    {
        return [
            "from" => "supun@hyvor.com",
            "to" => "somebody@example.com",
            "body_html" => str_repeat('a', 2 * 1024 * 1024 + 1), // 2MB + 1
        ];
    }

    protected function getTextBodyTooLargeData(): mixed
    {
        return [
            "from" => "supun@hyvor.com",
            "to" => "somebody@example.com",
            "body_text" => str_repeat('a', 2 * 1024 * 1024 + 1), // 2MB + 1
        ];
    }

    protected function getAttachmentMaxSizeForEachData(): mixed
    {
        return [
            "from" => "supun@hyvor.com",
            "to" => "somebody@example.com",
            "body_text" => 'test',
            'attachments' => [
                [
                    'content' => str_repeat('a', 10 * 1024 * 1024 + 1),
                    'name' => 'large.txt',
                    'content_type' => 'text/plain'
                ],
            ]
        ];
    }

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
    #[ // body_text to large
        TestWith([
            'getTextBodyTooLargeData',
            "body_text",
            "body_text must not exceed 2MB.",
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
    #[ // body_html to large
        TestWith([
            'getHtmlBodyTooLargeData',
            "body_html",
            "body_html must not exceed 2MB.",
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
    #[ // attachments content required
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'attachments' => [
                    ['content' => '']
                ]
            ],
            "attachments[0][content]",
            "This value should not be blank.",
        ])
    ]
    #[ // attachments content non-string
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'attachments' => [
                    [
                        'content' => 123,
                        'name' => 'file.txt',
                        'content_type' => 'text/plain',
                    ]
                ]
            ],
            "attachments[0][content]",
            "This value should be of type string.",
        ])
    ]
    #[ // attachments max limit
        TestWith([
            [
                "from" => "supun@hyvor.com",
                "to" => "somebody@example.com",
                "body_text" => 'test',
                'attachments' => [
                    ['content' => '1'],
                    ['content' => '2'],
                    ['content' => '3'],
                    ['content' => '4'],
                    ['content' => '5'],
                    ['content' => '6'],
                    ['content' => '7'],
                    ['content' => '8'],
                    ['content' => '9'],
                    ['content' => '10'],
                    ['content' => '11'], // 11th attachment
                ]
            ],
            "attachments",
            "You can attach a maximum of 10 files.",
        ])
    ]
    #[ // attachments max size for each
        TestWith([
            'getAttachmentMaxSizeForEachData',
            "attachments[0][content]",
            "Attachment content must not exceed 10MB.",
        ])
    ]
    public function test_validation(
        array|string $data,
        string $property,
        string $violationMessage
    ): void {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
        ]);

        if (is_string($data)) {
            /** @var callable(): array<string, mixed> $callable */
            $callable = [$this, $data];
            $data = call_user_func($callable);
        }

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
            'status' => DomainStatus::ACTIVE,
            'dkim_selector' => 'my-selector'
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
                    'Reply-To' => 'no-reply@hyvor.com', // bug #163
                ],
            ],
            scopes: [Scope::SENDS_SEND]
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

        $recipients = $send->getRecipients();
        $this->assertCount(1, $recipients);
        $recipient = $recipients[0];
        $this->assertSame($toAddress, $recipient->getAddress());
        $this->assertSame(SendRecipientType::TO, $recipient->getType());

        if ($useArrayAddress) {
            $this->assertSame("Supun", $send->getFromName());
            $this->assertSame("Somebody", $recipient->getName());
        } else {
            $this->assertEmpty($send->getFromName());
            $this->assertEmpty($recipient->getName());
        }

        $this->assertSame(
            [
                "X-Custom-Header" => "Custom Value",
                'Reply-To' => 'no-reply@hyvor.com'
            ],
            $send->getHeaders()
        );

        $raw = $send->getRaw();

        $rawSplit = explode("\r\n\r\n", $raw, 2);
        $rawHeaders = $rawSplit[0];
        $rawBody = $rawSplit[1];

        $fromHeader = $useArrayAddress ? "Supun <supun@hyvor.com>" : "supun@hyvor.com";
        $toHeader = $useArrayAddress ? "Somebody <somebody@example.com>" : "somebody@example.com";
        $this->assertStringNotContainsString('CC:', $rawHeaders);
        $this->assertStringNotContainsString('BCC:', $rawHeaders);
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

        preg_match_all('/^DKIM-Signature:.*?(?:\r\n[ \t].*?)*(?=\r\n\S)/ms', $rawHeaders, $matches);

        $first = $matches[0][0];
        $first = str_replace("\r\n", "", $first);
        $this->assertStringContainsString(
            "h=From: To: Subject: X-Custom-Header: Reply-To: Message-ID: X-Mailer: MIME-Version: Date;",
            $first
        );
        $this->assertStringContainsString("i=@hyvor.com", $first);
        $this->assertStringContainsString("s=my-selector", $first);

        $second = $matches[0][1];
        $second = str_replace("\r\n", "", $second);
        $this->assertStringContainsString(
            "h=From: To: Subject: X-Custom-Header: Reply-To: Message-ID: X-Mailer: MIME-Version: Date;",
            $second
        );
        $this->assertStringContainsString("i=@relay.hyvor.localhost", $second);
        $this->assertStringContainsString("s=default", $second);
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
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertSame(
            "Domain hyvor.com is not allowed to send emails (status: pending)",
            $json['message']
        );
    }

    public function test_does_not_allow_suppressed_emails(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE,
        ]);

        SuppressionFactory::createOne([
            'project' => $project,
            'email' => 'test@example.com'
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertSame(
            "Email address test@example.com is suppressed",
            $json['message']
        );
    }

    public function test_with_attachments(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE,
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
            'body_html' => '<p>Test email</p>',
            'attachments' => [
                [
                    'content' => base64_encode('This is a test file.'),
                    'name' => 'test.txt',
                    'content_type' => 'text/plain',
                ],
                [
                    'content' => base64_encode('This is another test file.'),
                    'name' => 'test2.txt',
                    'content_type' => 'text/plain',
                ]
            ]
        ]);

        $this->assertResponseStatusCodeSame(200);

        $send = $this->em->getRepository(Send::class)->findBy([
            'project' => $project->getId(),
        ]);
        $this->assertCount(1, $send);

        $send = $send[0];

        $rawEmail = $send->getRaw();

        $this->assertStringContainsString("Content-Type: multipart/mixed; boundary=", $rawEmail);
        $this->assertStringContainsString("Content-Type: text/plain;", $rawEmail);
        $this->assertStringContainsString(base64_encode('This is a test file.'), $rawEmail);
        $this->assertStringContainsString(
            "Content-Disposition: attachment; name=test.txt; filename=test.txt",
            $rawEmail
        );
        $this->assertStringContainsString("Content-Type: text/plain;", $rawEmail);
        $this->assertStringContainsString(base64_encode('This is another test file.'), $rawEmail);
        $this->assertStringContainsString(
            "Content-Disposition: attachment; name=test2.txt; filename=test2.txt",
            $rawEmail
        );
    }

    public function test_fails_gracefully_when_cannot_decode_attachments(): void
    {
        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE,
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
            'attachments' => [
                [
                    'content' => base64_encode('This is a test file.') . 'INVALID',
                    'name' => 'test.txt',
                    'content_type' => 'text/plain',
                ],
            ]
        ]);

        $this->assertResponseStatusCodeSame(400);
        $json = $this->getJson();
        $this->assertSame(
            "Base64 decoding of attachment failed: index 0",
            $json['message']
        );
    }

    public function test_email_max10mb(): void
    {
        /*
         Note: this is slow because of DKIM signing. DkimSigner::hashBody is the bottleneck
         $_ENV['start'] = microtime(true);
        dd(microtime(true) - $_ENV['start']);
        Update: moved the size check to before DKIM signing in EmailBuilder.
        */


        ini_set('memory_limit', '256M');

        QueueFactory::createTransactional();
        $project = ProjectFactory::createOne();

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE,
        ]);

        $attachment = [
            'content' => base64_encode(str_repeat('a', 4 * 1024 * 1024)), // 4MB
        ];

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
            'attachments' => [
                // total 12MB
                $attachment,
                $attachment,
                $attachment
            ]
        ]);

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertSame(
            "Email size exceeds the maximum allowed size of 10MB.",
            $json['message']
        );
    }

    public function test_queues_on_the_correct_queue_based_on_project_send_type(): void
    {
        QueueFactory::createDistributional();

        $project = ProjectFactory::createOne([
            'send_type' => ProjectSendType::DISTRIBUTIONAL
        ]);

        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE,
        ]);

        $this->consoleApi($project, "POST", "/sends", data: [
            'from' => 'test@hyvor.com',
            'to' => 'test@example.com',
            'body_text' => 'Test email',
        ]);

        $this->assertResponseStatusCodeSame(200);

        $send = $this->em->getRepository(Send::class)->findBy(['project' => $project->getId()]);
        $this->assertCount(1, $send);
        $send = $send[0];
        $this->assertSame('distributional', $send->getQueue()->getName());
    }

}
