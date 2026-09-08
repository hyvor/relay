<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Object\SuppressionObject;
use App\Entity\Project;
use App\Entity\Suppression;
use App\Entity\Type\SuppressionReason;
use App\Service\Suppression\SuppressionService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SuppressionsController extends AbstractController
{
    public function __construct(
        private SuppressionService $suppressionService
    ) {
    }

    #[Route('/suppressions', methods: 'GET')]
    #[ScopeRequired(Scope::SUPPRESSIONS_READ)]
    #[OA\Get(
        summary: 'Get all suppressions',
        description: 'Returns suppressed email addresses for the project.'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of suppressions',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: SuppressionObject::class))
        )
    )]
    public function list(Request $request, Project $project): JsonResponse
    {
        $limit = $request->query->getInt("limit", 50);
        $offset = $request->query->getInt("offset", 0);

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
            ->getSuppressionsForProject($project, $emailSearch, $reason, $limit, $offset)
            ->map(fn($suppresion) => new SuppressionObject($suppresion));

        return $this->json($suppressions);
    }

    #[Route('/suppressions/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::SUPPRESSIONS_WRITE)]
    #[OA\Delete(
        summary: 'Delete a suppression',
        description: 'Removes an email address from the project suppressions list.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns an empty object on success.',
        content: new OA\JsonContent()
    )]
    public function delete(Suppression $suppression): JsonResponse
    {
        $this->suppressionService->deleteSuppression($suppression);

        return $this->json([]);
    }
}
