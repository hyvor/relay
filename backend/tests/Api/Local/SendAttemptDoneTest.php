<?php

namespace App\Tests\Api\Local;

use App\Service\Send\Event\SendAttemptCreatedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\SendAttemptFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;

class SendAttemptDoneTest extends WebTestCase
{

    public function test_dispatches_events(): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);

        $attempt1 = SendAttemptFactory::createOne();
        $attempt2 = SendAttemptFactory::createOne();

        $this->localApi(
            "POST",
            "/send-attempts/done",
            [
                'send_attempt_ids' => [
                    $attempt1->getId(),
                    $attempt2->getId(),
                    3 // ignored
                ],
            ],
        );

        $this->assertResponseIsSuccessful();

        $eventDispatcher->assertDispatchedCount(SendAttemptCreatedEvent::class, 2);
    }

}