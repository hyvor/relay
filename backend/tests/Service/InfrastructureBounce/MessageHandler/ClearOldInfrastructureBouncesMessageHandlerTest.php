<?php

namespace App\Tests\Service\InfrastructureBounce\MessageHandler;

use App\Entity\InfrastructureBounce;
use App\Service\InfrastructureBounce\Message\ClearOldInfrastructureBouncesMessage;
use App\Service\InfrastructureBounce\MessageHandler\ClearOldInfrastructureBouncesMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClearOldInfrastructureBouncesMessageHandler::class)]
class ClearOldInfrastructureBouncesMessageHandlerTest extends KernelTestCase
{

    public function test_deletes(): void
    {
        $bounce1 = InfrastructureBounceFactory::createOne(['created_at' => new \DateTimeImmutable('-2 years')]);
        $bounce2 = InfrastructureBounceFactory::createOne(['created_at' => new \DateTimeImmutable('-2 months')]);
        $bounce3 = InfrastructureBounceFactory::createOne(['created_at' => new \DateTimeImmutable('-30 days')]);
        $bounce4 = InfrastructureBounceFactory::createOne(['created_at' => new \DateTimeImmutable('-1 week')]);
        $bounce5 = InfrastructureBounceFactory::createOne(['created_at' => new \DateTimeImmutable('-1 day')]);

        $transport = $this->transport('scheduler_default');
        $transport->send(new ClearOldInfrastructureBouncesMessage());
        $transport->throwExceptions()->process();

        $bounces = $this->em->getRepository(InfrastructureBounce::class)->findAll();
        $this->assertCount(2, $bounces);

        $bounceIds = array_map(fn(InfrastructureBounce $bounce) => $bounce->getId(), $bounces);
        $this->assertContains($bounce4->getId(), $bounceIds);
        $this->assertContains($bounce5->getId(), $bounceIds);
    }

}

