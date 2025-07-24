<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Entity\Type\SendStatus;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[CoversClass(LocalController::class)]
class SendDoneTest extends WebTestCase
{

    public function test_cannot_call_from_non_localhost_ip(): void
    {
        $response = $this->localApi(
            "POST",
            "/send/done",
            [
                "sendId" => 9999,
                "status" => "accepted",
            ],
            server: [
                'REMOTE_ADDR' => '8.8.8.8'
            ]
        );

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('Only requests from localhost are allowed.', $this->getJson()["message"]);
    }

    public function test_fails_on_no_send_found(): void
    {
        $this->localApi("POST", "/send/done", [
            "sendId" => 9999,
            "status" => "accepted",
        ]);

        $this->assertResponseStatusCodeSame(422);

        $json = $this->getJson();
        $this->assertSame("Send not found", $json["message"]);
    }

    public function test_successfully_marks_send_as_sent(): void
    {
        $time = new \DateTimeImmutable();
        Clock::set(new MockClock($time));

        $send = SendFactory::createOne([
            "status" => SendStatus::QUEUED,
            "sent_at" => null,
            "failed_at" => null,
        ]);

        $response = $this->localApi("POST", "/send/done", [
            "sendId" => $send->getId(),
            "status" => "accepted",
            "result" => '{}',
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(SendStatus::ACCEPTED, $send->getStatus());
        $this->assertSame($time->getTimestamp(), $send->getSentAt()?->getTimestamp());
        $this->assertNull($send->getFailedAt());
        $this->assertSame('{}', $send->getResult());
    }

    public function test_successfully_marks_send_as_failed(): void
    {
        $time = new \DateTimeImmutable();
        Clock::set(new MockClock($time));

        $send = SendFactory::createOne([
            "status" => SendStatus::QUEUED,
            "sent_at" => null,
            "failed_at" => null,
        ]);

        $this->localApi("POST", "/send/done", [
            "sendId" => $send->getId(),
            "status" => "complained",
            "result" => '{}',
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(SendStatus::COMPLAINED, $send->getStatus());
        $this->assertNull($send->getSentAt());
        $this->assertSame($time->getTimestamp(), $send->getFailedAt()?->getTimestamp());
        $this->assertSame('{}', $send->getResult());
    }
}
