<?php

namespace App\Tests\Service\Send\MessageHandler;

use App\Entity\Send;
use App\Service\Send\Message\ClearExpiredSendsMessage;
use App\Service\Send\MessageHandler\ClearExpiredSendsMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClearExpiredSendsMessageHandler::class)]
class ClearExpiredSendsMessageHandlerTest extends KernelTestCase
{

    public function test_deletes(): void
    {
        $send1 = SendFactory::createOne(['createdAt' => new \DateTimeImmutable('-2 years')]);
        $send2 = SendFactory::createOne(['createdAt' => new \DateTimeImmutable('-2 months')]);
        $send3 = SendFactory::createOne(['createdAt' => new \DateTimeImmutable('-30 days')]);
        $send4 = SendFactory::createOne(['createdAt' => new \DateTimeImmutable('-1 week')]);
        $send5 = SendFactory::createOne(['createdAt' => new \DateTimeImmutable('-1 day')]);

        $transport = $this->transport('scheduler_default');
        $transport->send(new ClearExpiredSendsMessage());
        $transport->throwExceptions()->process();

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(2, $sends);

        $sendsIds = array_map(fn(Send $send) => $send->getId(), $sends);
        $this->assertContains($send4->getId(), $sendsIds);
        $this->assertContains($send5->getId(), $sendsIds);
    }

}