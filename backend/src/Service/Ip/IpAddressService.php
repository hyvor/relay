<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Entity\Server;
use App\Entity\Type\WarmupStatus;
use App\Service\Ip\Dto\PtrValidationDto;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Ip\Event\IpRemovedEvent;
use App\Service\Queue\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IpAddressService
{

    use ClockAwareTrait;

    public function __construct(
        private ServerIp $serverIp,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private Ptr $ptr,
        private QueueService $queueService,
    ) {
    }

    /**
     * @return IpAddress[]
     */
    public function getAllIpAddresses(): array
    {
        return $this->em->getRepository(IpAddress::class)->findBy(
            [],
            ['id' => 'ASC']
        );
    }

    public function getIpAddressesCount(): int
    {
        return $this->em->getRepository(IpAddress::class)->count([]);
    }

    public function getIpAddressById(int $id): ?IpAddress
    {
        return $this->em->getRepository(IpAddress::class)->find($id);
    }

    /**
     * @return IpAddress[]
     */
    public function getIpAddressesOfServer(Server $server): array
    {
        return $this->em->getRepository(IpAddress::class)->findBy(
            ['server' => $server],
            ['id' => 'ASC']
        );
    }

    public function getIpForQueue(Queue $queue, int $recipientCount = 1): ?IpAddress
    {
        /** @var IpAddress[] $ips */
        $ips = $this->em->getRepository(IpAddress::class)->findBy([
            'queue' => $queue,
        ]);

        if (empty($ips)) {
            return null;
        }

        shuffle($ips);

        foreach ($ips as $ip) {
            if (!$ip->isWarmingUp()) {
                return $ip;
            }
        }

        $conn = $this->em->getConnection();
        $warmupStatus = WarmupStatus::WARMING->value;

        foreach ($ips as $ip) {
            if ($ip->isWarmingUp()) {
                $rows = $conn->executeStatement(
                    'UPDATE ip_addresses SET warmup_sent_today = warmup_sent_today + :count WHERE id = :id AND warmup_status = :status AND warmup_sent_today + :count <= warmup_max_today',
                    [
                        'count' => $recipientCount,
                        'id' => $ip->getId(),
                        'status' => $warmupStatus,
                    ]
                );

                if ($rows > 0) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Creates IP address records if not already present.
     * Deletes IP address records that are not present in the server's current IP addresses.
     */
    public function updateIpAddressesOfServer(Server $server): void
    {
        $currentIpAddressesEntitiesInDb = $this->getIpAddressesOfServer($server);
        $currentIpAddressesInDb = array_map(fn(IpAddress $ip) => $ip->getIpAddress(), $currentIpAddressesEntitiesInDb);
        $serverIpAddresses = $this->serverIp->getPublicV4IpAddresses();

        // Create IP addresses that are in the server's current IP addresses but not in the database
        foreach ($serverIpAddresses as $serverIpAddress) {
            $inArrayKey = in_array($serverIpAddress, $currentIpAddressesInDb);
            if ($inArrayKey === false) {
                $this->createIpAddress($server, $serverIpAddress);
            }
        }

        // Delete IP addresses that are in the database but not in the server's current IP addresses
        $ipAddressesToDelete = array_filter(
            $currentIpAddressesEntitiesInDb,
            fn(IpAddress $ip) => !in_array($ip->getIpAddress(), $serverIpAddresses)
        );
        foreach ($ipAddressesToDelete as $ipAddress) {
            $this->deleteIpAddress($ipAddress);
        }
    }

    public function createIpAddress(Server $server, string $ipAddress): IpAddress
    {
        $ipAddressEntity = new IpAddress();
        $ipAddressEntity->setServer($server);
        $ipAddressEntity->setIpAddress($ipAddress);
        $ipAddressEntity->setCreatedAt($this->now());
        $ipAddressEntity->setUpdatedAt($this->now());
        $ipAddressEntity->setQueue($this->queueService->getAQueueThatHasNoIpAddresses());

        $this->em->persist($ipAddressEntity);
        $this->em->flush();

        return $ipAddressEntity;
    }

    public function deleteIpAddress(IpAddress $ipAddress): void
    {
        $this->em->remove($ipAddress);
        $this->em->flush();

        $this->ed->dispatch(new IpRemovedEvent($ipAddress));
    }

    public function updateIpAddress(
        IpAddress $ipAddress,
        UpdateIpAddressDto $updates
    ): IpAddress {
        $ipAddressOld = clone $ipAddress;

        if ($updates->queueSet) {
            $ipAddress->setQueue($updates->queue);
        }

        if ($updates->warmupScheduleSet) {
            $ipAddress->setWarmupSchedule($updates->warmup_schedule);
        }

        if ($updates->warmupStatusSet) {
            $ipAddress->setWarmupStatus($updates->warmup_status);
        }

        if (
            $ipAddress->getWarmupStatus() === \App\Entity\Type\WarmupStatus::WARMING
            && $ipAddress->getWarmupSchedule() !== null
        ) {
            $ipAddress->setWarmupStartedDate($this->now()->setTime(0, 0));
            $ipAddress->setWarmupSentToday(0);
            $schedule = $ipAddress->getWarmupSchedule();
            if (count($schedule) > 0) {
                $ipAddress->setWarmupMaxToday($schedule[0]);
            }
        }

        if (
            $updates->warmupStatusSet
            && $ipAddress->getWarmupStatus() === \App\Entity\Type\WarmupStatus::WARMED
        ) {
            $ipAddress->setWarmupSentToday(0);
            $ipAddress->setWarmupMaxToday(0);
        }

        $ipAddress->setUpdatedAt($this->now());

        $this->em->persist($ipAddress);
        $this->em->flush();

        $event = new IpAddressUpdatedEvent($ipAddressOld, $ipAddress, $updates);
        $this->ed->dispatch($event);

        return $ipAddress;
    }

    /**
     * @return array{forward: PtrValidationDto, reverse: PtrValidationDto}
     */
    public function updateIpPtrValidity(IpAddress $ip): array
    {
        $validity = $this->ptr->validate($ip);

        $ip->setIsPtrForwardValid($validity['forward']->valid);
        $ip->setIsPtrReverseValid($validity['reverse']->valid);

        $this->em->persist($ip);
        $this->em->flush();

        return $validity;
    }

}
