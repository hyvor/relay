<?php

namespace App\Service\Send;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Clock\ClockAwareTrait;

class SendAnalyticsService
{

    use ClockAwareTrait;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, int>
     */
    public function getLast30dCounts(Project $project): array
    {
        $qb = $this->em->createQuery(<<<DQL
            SELECT 
                COUNT(sr.id) AS total,
                SUM(CASE WHEN sr.status = 'bounced' THEN 1 ELSE 0 END) AS bounced,
                SUM(CASE WHEN sr.status = 'complained' THEN 1 ELSE 0 END) AS complained
            FROM App\Entity\Send s
            JOIN s.recipients sr
            WHERE 
                s.project = :project AND
                s.created_at >= :date
        DQL);

        $qb->setParameter('project', $project);
        $qb->setParameter('date', new \DateTime('-30 days'));

        /** @var array{total: int, bounced: int, complained: int} $result */
        $result = $qb->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'bounced' => (int) $result['bounced'],
            'complained' => (int) $result['complained'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function getSendsChartData(Project $project): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('date', 'date');
        $rsm->addScalarResult('total', 'total', 'integer');
        $rsm->addScalarResult('bounced', 'bounced', 'integer');
        $rsm->addScalarResult('complained', 'complained', 'integer');
        $rsm->addScalarResult('accepted', 'accepted', 'integer');
        $rsm->addScalarResult('queued', 'queued', 'integer');

        $qb = $this->em->createNativeQuery(<<<SQL
        SELECT DATE(s.created_at) AS date,
            COUNT(sr.id) AS total,
            SUM(CASE WHEN sr.status = 'bounced' THEN 1 ELSE 0 END) AS bounced,
            SUM(CASE WHEN sr.status = 'complained' THEN 1 ELSE 0 END) AS complained,
            SUM(CASE WHEN sr.status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN sr.status = 'queued' THEN 1 ELSE 0 END) AS queued
        FROM sends s
        JOIN send_recipients sr ON sr.send_id = s.id
        WHERE 
            s.project_id = :projectId AND
            s.created_at >= :date
        GROUP BY date
        SQL, $rsm);

        $startDate = $this->now()->modify('-30 days');
        $qb->setParameter('projectId', $project->getId());
        $qb->setParameter('date', $startDate);

        /** @var array{date: string, total: int, bounced:int, complained: int, accepted:int, queued:int}[] $result */
        $result = $qb->getResult();

        $data = [];
        $currentDate = clone $startDate;
        $endDate = $this->now();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            $row = array_filter($result, fn ($r) => $r['date'] === $dateStr);
            $row = count($row) ? array_shift($row) : null;

            $data[] = [
                'date' => $dateStr,
                'total' => $row['total'] ?? 0,
                'bounced' => $row['bounced'] ?? 0,
                'complained' => $row['complained'] ?? 0,
                'accepted' => $row['accepted'] ?? 0,
                'queued' => $row['queued'] ?? 0,
            ];
            $currentDate = $currentDate->modify('+1 day');
        }

        return $data;

    }


}
