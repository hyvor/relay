<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Type\WarmupStatus;
use App\Entity\WarmupSchedule;
use App\Service\Ip\Dto\UpdateWarmupScheduleDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class WarmupScheduleService
{

    use ClockAwareTrait;

    /**
     * @var array<int>
     */
    public const DEFAULT_SCHEDULE = [
        50, 100, 250, 500, 1000,
        2500, 5000, 10000, 10000, 20000, 20000,
        40000, 40000, 75000, 150000, 150000, 150000,
        150000, 150000, 300000, 300000, 300000, 300000,
        300000, 300000, 500000, 500000, 500000, 1000000, 1000000,
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return WarmupSchedule[]
     */
    public function getWarmupSchedules(?int $ipAddressId = null): array
    {
        $criteria = [];
        if ($ipAddressId !== null) {
            $criteria['ip_address'] = $ipAddressId;
        }

        return $this->em->getRepository(WarmupSchedule::class)->findBy(
            $criteria,
            ['id' => 'DESC']
        );
    }

    public function getWarmupScheduleById(int $id): ?WarmupSchedule
    {
        return $this->em->getRepository(WarmupSchedule::class)->find($id);
    }

    /**
     * @param array<int> $schedule
     */
    public function createWarmupSchedule(
        IpAddress $ipAddress,
        array $schedule,
    ): WarmupSchedule {
        $warmup = new WarmupSchedule();
        $warmup->setIpAddress($ipAddress);
        $warmup->setCreatedAt($this->now());
        $warmup->setUpdatedAt($this->now());
        $warmup->setStatus(WarmupStatus::WARMING);
        $warmup->setStartedDate($this->now()->setTime(0, 0));
        $warmup->setSentToday(0);
        $warmup->setSchedule($schedule);

        if (count($schedule) > 0) {
            $warmup->setMaxToday($schedule[0]);
        }

        $this->em->persist($warmup);
        $this->em->flush();

        return $warmup;
    }

    public function updateWarmupSchedule(
        WarmupSchedule $warmup,
        UpdateWarmupScheduleDto $updates,
    ): WarmupSchedule {
        if ($updates->scheduleSet) {
            if ($updates->schedule === null) {
                throw new \InvalidArgumentException("schedule cannot be null when scheduleSet is true");
            }
            $warmup->setSchedule($updates->schedule);
        }

        if ($updates->statusSet) {
            $status = $updates->status;
            if ($status === null) {
                throw new \InvalidArgumentException("status cannot be null when statusSet is true");
            }
            $warmup->setStatus($status);

            if ($status === WarmupStatus::WARMING && count($warmup->getSchedule()) > 0) {
                $warmup->setStartedDate($this->now()->setTime(0, 0));
                $warmup->setSentToday(0);
                $warmup->setMaxToday($warmup->getSchedule()[0]);
            } elseif ($status === WarmupStatus::WARMED || $status === WarmupStatus::CANCELLED) {
                $warmup->setSentToday(0);
                $warmup->setMaxToday(0);
            }
        }

        $warmup->setUpdatedAt($this->now());
        $this->em->persist($warmup);
        $this->em->flush();

        return $warmup;
    }

    public function deleteWarmupSchedule(WarmupSchedule $warmup): void
    {
        $this->em->remove($warmup);
        $this->em->flush();
    }

    public function getCurrentWarmupSchedule(IpAddress $ipAddress): ?WarmupSchedule
    {
        return $this->em->getRepository(WarmupSchedule::class)->findOneBy([
            'ip_address' => $ipAddress,
            'status' => WarmupStatus::WARMING,
        ]);
    }

    /**
     * @param IpAddress[] $ipAddresses
     * @return array<int, WarmupSchedule>
     */
    public function getCurrentWarmupSchedulesByIpAddresses(array $ipAddresses): array
    {
        if (empty($ipAddresses)) {
            return [];
        }

        /** @var WarmupSchedule[] $results */
        $results = $this->em->createQueryBuilder()
            ->select('ws')
            ->from(WarmupSchedule::class, 'ws')
            ->join('ws.ip_address', 'ip')
            ->where('ws.ip_address IN (:ipAddresses)')
            ->andWhere('ws.status = :status')
            ->setParameter('ipAddresses', $ipAddresses)
            ->setParameter('status', WarmupStatus::WARMING)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($results as $ws) {
            $indexed[$ws->getIpAddress()->getId()] = $ws;
        }

        return $indexed;
    }
}
