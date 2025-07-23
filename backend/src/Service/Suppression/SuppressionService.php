<?php

namespace App\Service\Suppression;

use App\Entity\Project;
use App\Entity\Suppression;
use App\Entity\Type\SuppressionReason;
use App\Repository\SuppressionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class SuppressionService
{
    public function __construct(
        private SuppressionRepository $suppressionRepository,
        private EntityManagerInterface $em
    )
    {
    }

    /**
     * @return ArrayCollection<int, Suppression>
     */
    public function getSuppressionsForProject(Project $project, ?string $email, ?SuppressionReason $reason = null): ArrayCollection
    {
        $qb = $this->suppressionRepository->createQueryBuilder('s');

        $qb
            ->distinct()
            ->where('s.project = :project')
            ->setParameter('project', $project)
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

    public function deleteSuppression(Suppression $suppression): void
    {
        $this->em->remove($suppression);
        $this->em->flush();
    }
}