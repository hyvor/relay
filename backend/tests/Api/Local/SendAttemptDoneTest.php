<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Service\Send\Event\SendAttemptCreatedEvent;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\SendAttemptFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
#[CoversClass(SendService::class)]
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
                    3423 // ignored
                ],
            ],
        );

        $this->assertResponseIsSuccessful();

        $eventDispatcher->assertDispatchedCount(SendAttemptCreatedEvent::class, 2);
    }

}