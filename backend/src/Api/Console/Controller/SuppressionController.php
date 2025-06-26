<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Object\SuppressionObject;
use App\Entity\Project;
use App\Entity\Suppression;
use App\Service\Suppression\SuppressionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
class SuppressionController extends AbstractController
{
    public function __construct(
        private SuppressionService $suppressionService
    )
    {
    }

    #[Route('/suppressions', methods: 'GET')]
    public function getSuppressions(Request $request, Project $project): JsonResponse
    {
        $emailSearch = null;
        if ($request->query->has('email')) {
            $emailSearch = $request->query->getString('email');
        }

        $suppressions = $this
            ->suppressionService
            ->getSuppressionsForProject($project, $emailSearch)
            ->map(fn($suppresion) => new SuppressionObject($suppresion));

        return $this->json($suppressions);
    }

    #[Route('/suppressions/{id}', methods: 'DELETE')]
    public function deleteSuppression(Suppression $suppression): JsonResponse
    {
        $this->suppressionService->deleteSuppression($suppression);

        return $this->json([]);
    }
}