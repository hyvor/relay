<?php

namespace Api\Local;

use App\Entity\Type\SendStatus;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\SendFactory;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

class SendDoneTest extends WebTestCase
{

    public function test_cannot_call_from_non_localhost_ip(): void
    {
        //
    }

    public function test_fails_on_no_send_found(): void
    {
        $this->localApi("POST", "/send/done", [
            "sendId" => 9999,
            "status" => "sent",
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

        $this->localApi("POST", "/send/done", [
            "sendId" => $send->getId(),
            "status" => "sent",
            "result" => '{}',
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(SendStatus::SENT, $send->getStatus());
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
            "status" => "failed",
            "result" => '{}',
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(SendStatus::FAILED, $send->getStatus());
        $this->assertNull($send->getSentAt());
        $this->assertSame($time->getTimestamp(), $send->getFailedAt()?->getTimestamp());
        $this->assertSame('{}', $send->getResult());
    }
}
