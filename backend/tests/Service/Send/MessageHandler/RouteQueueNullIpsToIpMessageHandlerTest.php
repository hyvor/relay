<?php

namespace App\Tests\Service\Send\MessageHandler;

use App\Entity\Send;
use App\Entity\Type\SendRecipientStatus;
use App\Repository\SendRepository;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use App\Service\Send\MessageHandler\RouteQueueNullIpsToIpMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RouteQueueNullIpsToIpMessageHandler::class)]
#[CoversClass(RouteQueueNullIpsToIpMessage::class)]
class RouteQueueNullIpsToIpMessageHandlerTest extends KernelTestCase
{

	public function test_reassigns_null_ip_sends_to_specific_ip(): void
	{
		$queue = QueueFactory::createOne();
		$ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

		$send = SendFactory::createOne([
			'queue' => $queue,
			'ip_address' => null,
		]);

		SendRecipientFactory::createOne([
			'send' => $send,
			'status' => SendRecipientStatus::QUEUED,
		]);

		$transport = $this->transport('async');
		$transport->send(new RouteQueueNullIpsToIpMessage($queue->getId(), $ipAddress->getId()));
		$transport->throwExceptions()->process();

		$this->em->clear();

		/** @var SendRepository $sendRepo */
		$sendRepo = $this->em->getRepository(Send::class);
		$updatedSend = $sendRepo->find($send->getId());

		$this->assertNotNull($updatedSend);
		$this->assertNotNull($updatedSend->getIpAddress());
		$this->assertSame($ipAddress->getId(), $updatedSend->getIpAddress()->getId());
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
		$transport->send(new RouteQueueNullIpsToIpMessage($queue->getId(), $ip2->getId()));
		$transport->throwExceptions()->process();

		$this->em->clear();

		/** @var SendRepository $sendRepo */
		$sendRepo = $this->em->getRepository(Send::class);
		$updatedSend = $sendRepo->find($send->getId());

		$this->assertNotNull($updatedSend);
		$this->assertNotNull($updatedSend->getIpAddress());
		$this->assertSame($ip1->getId(), $updatedSend->getIpAddress()->getId());
	}

	public function test_no_op_when_ip_does_not_exist(): void
	{
		$queue = QueueFactory::createOne();

		$send = SendFactory::createOne([
			'queue' => $queue,
			'ip_address' => null,
		]);

		$transport = $this->transport('async');
		$transport->send(new RouteQueueNullIpsToIpMessage($queue->getId(), 99999));
		$transport->throwExceptions()->process();

		$this->em->clear();

		/** @var SendRepository $sendRepo */
		$sendRepo = $this->em->getRepository(Send::class);
		$updatedSend = $sendRepo->find($send->getId());

		$this->assertNotNull($updatedSend);
		$this->assertNull($updatedSend->getIpAddress());
	}

	public function test_no_op_when_ip_belongs_to_different_queue(): void
	{
		$queue1 = QueueFactory::createOne();
		$queue2 = QueueFactory::createOne();
		$ipAddress = IpAddressFactory::createOne(['queue' => $queue2]);

		$send = SendFactory::createOne([
			'queue' => $queue1,
			'ip_address' => null,
		]);

		$transport = $this->transport('async');
		$transport->send(new RouteQueueNullIpsToIpMessage($queue1->getId(), $ipAddress->getId()));
		$transport->throwExceptions()->process();

		$this->em->clear();

		/** @var SendRepository $sendRepo */
		$sendRepo = $this->em->getRepository(Send::class);
		$updatedSend = $sendRepo->find($send->getId());

		$this->assertNotNull($updatedSend);
		$this->assertNull($updatedSend->getIpAddress());
	}
}
