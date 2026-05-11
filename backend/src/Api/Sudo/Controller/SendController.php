<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\SendObject;
use App\Entity\Type\SendRecipientStatus;
use App\Repository\ProjectRepository;
use App\Service\Send\SendService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class SendController extends AbstractController
{
    public function __construct(
        private SendService $sendService,
        private ProjectRepository $projectRepository,
    ) {}

    #[Route('/sends', methods: 'GET')]
    public function getSends(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 50);
        $beforeId = $request->query->has('before_id')
            ? $request->query->getInt('before_id')
            : null;

        $project = null;
        if ($request->query->has('project_id')) {
            $projectId = $request->query->getInt('project_id');
            $project = $this->projectRepository->find($projectId);
            if ($project === null) {
                throw new NotFoundHttpException('Project not found');
            }
        }

        $status = null;
        if ($request->query->has('status')) {
            $status = SendRecipientStatus::tryFrom($request->query->getString('status'));
        }

        $fromSearch = null;
        if ($request->query->has('from_search')) {
            $fromSearch = $request->query->getString('from_search');
        }

        $toSearch = null;
        if ($request->query->has('to_search')) {
            $toSearch = $request->query->getString('to_search');
        }

        $subjectSearch = null;
        if ($request->query->has('subject_search')) {
            $subjectSearch = $request->query->getString('subject_search');
        }

        $dateFromSearch = null;
        if ($request->query->has('date_from_search')) {
            $dateFromSearch = $request->query->getString('date_from_search');
        }

        $dateToSearch = null;
        if ($request->query->has('date_to_search')) {
            $dateToSearch = $request->query->getString('date_to_search');
        }

        $sends = $this->sendService
            ->getSends(
                $project,
                $status,
                $fromSearch,
                $toSearch,
                $subjectSearch,
                $dateFromSearch,
                $dateToSearch,
                $limit,
                $beforeId
            )
            ->map(fn($send) => new SendObject($send));

        return $this->json($sends);
    }
}
