<?php

namespace App\Tests\Service\Send\MessageHandler;

use App\Entity\Send;
use App\Entity\Type\SendRecipientStatus;
use App\Repository\SendRepository;
use App\Service\Send\Message\RouteNullIpsMessage;
use App\Service\Send\MessageHandler\RouteNullIpsMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RouteNullIpsMessageHandler::class)]
#[CoversClass(RouteNullIpsMessage::class)]
class RouteNullIpsMessageHandlerTest extends KernelTestCase
{

    public function test_reassigns_null_ip_sends_to_random_ip_in_queue(): void
    {
        $queue = QueueFactory::createOne();
        $ip1 = IpAddressFactory::createOne(['queue' => $queue]);
        $ip2 = IpAddressFactory::createOne(['queue' => $queue]);

        $send = SendFactory::createOne([
            'queue' => $queue,
            'ip_address' => $ip1,
		]);

		SendRecipientFactory::createOne([
			'send' => $send,
			'status' => SendRecipientStatus::QUEUED,
		]);

        // Simulate IP removal (delete IP and nullify sends)
        $this->em->remove($ip1->_real());
        $this->em->flush();

        $transport = $this->transport('async');
        $transport->send(new RouteNullIpsMessage($queue->getId()));
        $transport->throwExceptions()->process();

        $this->em->clear();

        /** @var SendRepository $sendRepo */
        $sendRepo = $this->em->getRepository(Send::class);
        $updatedSend = $sendRepo->find($send->getId());

        $this->assertNotNull($updatedSend);
        $this->assertNotNull($updatedSend->getIpAddress());
        $this->assertSame($ip2->getId(), $updatedSend->getIpAddress()->getId());
    }

    public function test_leaves_null_when_no_ip_available(): void
    {
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne([
            'queue' => $queue,
            'ip_address' => null,
        ]);

        $transport = $this->transport('async');
        $transport->send(new RouteNullIpsMessage($queue->getId()));
        $transport->throwExceptions()->process();

        $this->em->clear();

        /** @var SendRepository $sendRepo */
        $sendRepo = $this->em->getRepository(Send::class);
        $updatedSend = $sendRepo->find($send->getId());

        $this->assertNotNull($updatedSend);
        $this->assertNull($updatedSend->getIpAddress());
    }

    public function test_does_not_affect_sends_with_existing_ip(): void
    {
        $queue = QueueFactory::createOne();
        $ip1 = IpAddressFactory::createOne(['queue' => $queue]);
        $ip2 = IpAddressFactory::createOne(['queue' => $queue]);

        $send = SendFactory::createOne([
            'queue' => $queue,
            'ip_address' => $ip1,
        ]);

        $transport = $this->transport('async');
        $transport->send(new RouteNullIpsMessage($queue->getId()));
        $transport->throwExceptions()->process();

        $this->em->clear();

        /** @var SendRepository $sendRepo */
        $sendRepo = $this->em->getRepository(Send::class);
        $updatedSend = $sendRepo->find($send->getId());

        $this->assertNotNull($updatedSend);
        $this->assertNotNull($updatedSend->getIpAddress());
        $this->assertSame($ip1->getId(), $updatedSend->getIpAddress()->getId());
    }
}
