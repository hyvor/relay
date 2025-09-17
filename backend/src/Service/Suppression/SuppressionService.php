<?php

namespace App\Service\Suppression;

use App\Entity\Project;
use App\Entity\Suppression;
use App\Entity\Type\SuppressionReason;
use App\Repository\SuppressionRepository;
use App\Service\Suppression\Event\SuppressionCreatedEvent;
use App\Service\Suppression\Event\SuppressionDeletedEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SuppressionService
{
    use ClockAwareTrait;

    public function __construct(
        private SuppressionRepository $suppressionRepository,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return ArrayCollection<int, Suppression>
     */
    public function getSuppressionsForProject(
        Project $project,
        ?string $email,
        ?SuppressionReason $reason = null,
        int $limit = 50,
        int $offset = 0
    ): ArrayCollection {
        $qb = $this->suppressionRepository->createQueryBuilder('s');

        $qb
            ->distinct()
            ->where('s.project = :project')
            ->setParameter('project', $project)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('s.created_at', 'DESC');

        if ($email !== null) {
            $qb->andWhere('s.email LIKE :email')
                ->setParameter('email', '%' . $email . '%');
        }

        if ($reason !== null) {
            $qb->andWhere('s.reason = :reason')
                ->setParameter('reason', $reason);
        }

        // dd($qb->getQuery()->getSQL());
        /** @var Suppression[] $results */
        $results = $qb->getQuery()->getResult();

        return new ArrayCollection($results);
    }

    public function isSuppressed(Project $project, string $email): bool
    {
        return $this->suppressionRepository->findOneBy([
                'project' => $project,
                'email' => $email
            ]) !== null;
    }

    /**
     * @param Project $project
     * @param string[] $emails
     * @return string[] Suppressed emails
     */
    public function getSuppressed(Project $project, array $emails): array
    {
        /** @var Suppression[] $results */
        $results = $this->suppressionRepository->createQueryBuilder('s')
            ->where('s.project = :project')
            ->andWhere('s.email IN (:emails)')
            ->setParameter('project', $project)
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getResult();

        return array_map(fn(Suppression $s) => $s->getEmail(), $results);
    }

    public function createSuppression(
        Project $project,
        string $email,
        SuppressionReason $reason,
        string $description
    ): Suppression {
        $suppression = new Suppression();
        $suppression->setCreatedAt($this->now());
        $suppression->setUpdatedAt($this->now());
        $suppression->setProject($project);
        $suppression->setEmail($email);
        $suppression->setReason($reason);
        $suppression->setDescription($description);

        $this->em->persist($suppression);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new SuppressionCreatedEvent($suppression));

        return $suppression;
    }

    public function deleteSuppression(Suppression $suppression): void
    {
        $this->em->remove($suppression);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new SuppressionDeletedEvent($suppression));
    }
}
