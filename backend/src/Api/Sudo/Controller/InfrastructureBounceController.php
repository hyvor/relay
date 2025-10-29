<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\InfrastructureBounceObject;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class InfrastructureBounceController extends AbstractController
{
    public function __construct(
        private InfrastructureBounceService $infrastructureBounceService,
    ) {
    }

    #[Route('/infrastructure-bounces', methods: 'GET')]
    public function getInfrastructureBounces(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $offset = $request->query->getInt('offset', 0);
        
        $isRead = null;
        if ($request->query->has('is_read')) {
            $isRead = $request->query->getBoolean('is_read');
        }

        $bounces = $this->infrastructureBounceService->getInfrastructureBounces(
            $limit,
            $offset,
            $isRead
        );

        return new JsonResponse(
            array_map(fn($bounce) => new InfrastructureBounceObject($bounce), $bounces)
        );
    }

    #[Route('/infrastructure-bounces/{id}/mark-as-read', methods: 'PATCH')]
    public function markAsRead(int $id): JsonResponse
    {
        $bounce = $this->infrastructureBounceService->getInfrastructureBounceById($id);

        if (!$bounce) {
            throw new NotFoundHttpException('Infrastructure bounce not found');
        }

        $this->infrastructureBounceService->markAsRead($bounce);

        return new JsonResponse(new InfrastructureBounceObject($bounce));
    }

    #[Route('/infrastructure-bounces/mark-all-as-read', methods: 'POST')]
    public function markAllAsRead(): JsonResponse
    {
        $count = $this->infrastructureBounceService->markAllUnreadAsRead();

        return new JsonResponse([
            'marked_count' => $count,
        ]);
    }
}

