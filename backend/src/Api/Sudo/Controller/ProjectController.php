<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\ProjectObject;
use App\Repository\ProjectRepository;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {}

    #[Route('/projects', methods: 'GET')]
    public function getProjects(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 50);
        $beforeId = $request->query->has('before_id')
            ? $request->query->getInt('before_id')
            : null;

        $search = null;
        if ($request->query->has('search')) {
            $value = trim($request->query->getString('search'));
            if ($value !== '') {
                $search = $value;
            }
        }

        $qb = $this->projectRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        if ($beforeId !== null) {
            $qb->andWhere('p.id < :beforeId')
                ->setParameter('beforeId', $beforeId);
        }

        if ($search !== null) {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        /** @var \App\Entity\Project[] $projects */
        $projects = $qb->getQuery()->getResult();

        $objects = array_map(
            fn($project) => new ProjectObject($project),
            $projects
        );

        return $this->json($objects);
    }
}
