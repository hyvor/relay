<?php

namespace App\Tests\Service\SendRecipient;

use App\Service\SendRecipient\SendRecipientService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SendRecipientService::class)]
class SendRecipientServiceTest extends KernelTestCase
{

    public function test_send_recipients_by_send_attempt(): void
    {
        // included
        $recipient1 = SendRecipientFactory::createOne();

        // included
        $recipient2 = SendRecipientFactory::createOne();

        // not included
        SendRecipientFactory::createOne();

        $attempt = SendAttemptFactory::createOne([
            'recipient_results' => [
                ['recipient_id' => $recipient1->getId()],
                ['recipient_id' => $recipient2->getId()],
            ]
        ]);

        /** @var SendRecipientService $service */
        $service = $this->container->get(SendRecipientService::class);

        $results = $service->getSendRecipientsBySendAttempt($attempt);

        $this->assertCount(2, $results);
        $this->assertEquals(
            [$recipient1->getId(), $recipient2->getId()],
            array_map(fn($r) => $r->getId(), $results)
        );
    }

}