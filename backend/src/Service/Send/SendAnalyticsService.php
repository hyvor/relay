<?php

namespace App\Service\Send;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

class SendAnalyticsService
{

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, int>
     */
    public function getLast30dCounts(Project $project): array
    {
        $qb = $this->em->createQuery(<<<DQL
        SELECT COUNT(s.id) AS total,
               SUM(CASE WHEN s.status = 'bounced' THEN 1 ELSE 0 END) AS bounced,
               SUM(CASE WHEN s.status = 'complained' THEN 1 ELSE 0 END) AS complained
        FROM App\Entity\Send s
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

    public function getBounceCountLast30d(): float
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(b.id)')
            ->from('App\Entity\Bounce', 'b')
            ->where('b.createdAt >= :date')
            ->setParameter('date', new \DateTime('-30 days'));

        $bounceCount = (int) $qb->getQuery()->getSingleScalarResult();

        // Assuming total sends is the same as the sends count from getSendsLast30d
        $totalSends = $this->getSendsLast30d();

        return $totalSends > 0 ? ($bounceCount / $totalSends) : 0.0;
    }


}