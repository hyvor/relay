<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Object\SuppressionObject;
use App\Entity\Project;
use App\Entity\Suppression;
use App\Entity\Type\SuppressionReason;
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
    #[ScopeRequired(Scope::SUPPRESSIONS_READ)]
    public function getSuppressions(Request $request, Project $project): JsonResponse
    {
        $emailSearch = null;
        if ($request->query->has('email')) {
            $emailSearch = $request->query->getString('email');
        }

        $reason = null;
        if ($request->query->has('reason')) {
            $reasonValue = $request->query->getString('reason');
            $reason = SuppressionReason::tryFrom($reasonValue);
        }

        $suppressions = $this
            ->suppressionService
            ->getSuppressionsForProject($project, $emailSearch, $reason)
            ->map(fn($suppresion) => new SuppressionObject($suppresion));

        return $this->json($suppressions);
    }

    #[Route('/suppressions/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::SUPPRESSIONS_WRITE)]
    public function deleteSuppression(Suppression $suppression): JsonResponse
    {
        $this->suppressionService->deleteSuppression($suppression);

        return $this->json([]);
    }
}