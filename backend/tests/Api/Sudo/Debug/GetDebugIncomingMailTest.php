<?php

namespace App\Tests\Api\Sudo\Debug;

use App\Api\Sudo\Controller\DebugController;
use App\Api\Sudo\Object\DebugIncomingEmailObject;
use App\Entity\Type\DebugIncomingEmailType;
use App\Service\Debug\DebugIncomingMailService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DebugIncomingEmailFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DebugController::class)]
#[CoversClass(DebugIncomingMailService::class)]
#[CoversClass(DebugIncomingEmailObject::class)]
class GetDebugIncomingMailTest extends WebTestCase
{

    public function test_gets_incoming_mail(): void
    {
        $mail1 = DebugIncomingEmailFactory::createOne(['type' => DebugIncomingEmailType::BOUNCE]);
        $mail2 = DebugIncomingEmailFactory::createOne(['type' => DebugIncomingEmailType::COMPLAINT]);

        $this->sudoApi("GET", "/debug/incoming-mails");

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertCount(2, $json);
    }

}