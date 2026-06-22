<?php

namespace App\Api\Sudo\Controller;

use App\Api\Console\Object\SendObject;
use App\Api\Sudo\Object\SendProjectSummaryObject;
use App\Api\Sudo\Object\SudoSendObject;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Project\ProjectService;
use App\Service\Send\SendService;
use App\Service\SendAttempt\SendAttemptService;
use App\Service\SendFeedback\SendFeedbackService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class SendController extends AbstractController
{
    public function __construct(
        private SendService $sendService,
        private ProjectService $projectService,
        private SendAttemptService $sendAttemptService,
        private SendFeedbackService $sendFeedbackService,
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
            $project = $this->projectService->getProjectById($projectId);
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

        $sendEntities = $this->sendService->getSends(
            $project,
            $status,
            $fromSearch,
            $toSearch,
            $subjectSearch,
            $dateFromSearch,
            $dateToSearch,
            $limit,
            $beforeId
        );

        $sends = [];
        /** @var array<int, SendProjectSummaryObject> $projectsById */
        $projectsById = [];

        foreach ($sendEntities as $send) {
            $sends[] = new SudoSendObject($send);

            $sendProject = $send->getProject();
            $projectsById[$sendProject->getId()] = new SendProjectSummaryObject($sendProject);
        }

        return $this->json([
            'sends' => $sends,
            'projects' => array_values($projectsById),
        ]);
    }

    #[Route('/sends/uuid/{uuid}', requirements: ['uuid' => Requirement::UUID], methods: 'GET')]
    public function getByUuid(string $uuid): JsonResponse
    {
        $send = $this->sendService->getSendByUuid($uuid);

        if ($send === null) {
            throw new NotFoundHttpException("Send with UUID $uuid not found");
        }

        $attempts = $this->sendAttemptService->getSendAttemptsOfSend($send);
        $feedback = $this->sendFeedbackService->getFeedbackOfSend($send);

        return $this->json([
            'send' => new SendObject(
                $send,
                attempts: $attempts,
                feedback: $feedback,
                content: true
            ),
            'project' => new SendProjectSummaryObject($send->getProject()),
        ]);
    }
}
