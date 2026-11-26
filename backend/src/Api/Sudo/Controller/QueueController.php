<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\Queue\CreateQueueInput;
use App\Api\Sudo\Object\QueueObject;
use App\Entity\Queue;
use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class QueueController extends AbstractController
{

    public function __construct(
        private QueueService $queueService
    ) {}

    #[Route('/queues', methods: 'GET')]
    public function getQueues(): JsonResponse
    {
        $queues = $this->queueService->getAllQueues();
        $projectCounts = $this->queueService->getProjectCountsByQueue();

        $queueObjects = array_map(
            fn(Queue $queue) => new QueueObject($queue, $projectCounts[$queue->getId()] ?? 0),
            $queues
        );

        return $this->json($queueObjects);
    }

    #[Route('/queues', methods: 'POST')]
    public function createQueue(#[MapRequestPayload] CreateQueueInput $input): JsonResponse
    {
        if ($input->type === QueueType::DEDICATED) {
            // https://github.com/hyvor/relay/issues/83
            throw new BadRequestHttpException('Dedicated queues are not supported yet.');
        }

        if ($this->queueService->getQueue($input->type, $input->name) !== null) {
            throw new BadRequestHttpException("Queue {$input->type->value}:{$input->name} already exists.");
        }

        $queue = $this->queueService->createQueue($input->type, $input->name);

        return $this->json(new QueueObject($queue, 0));
    }

    #[Route('/queues/{id}', requirements: ['id' => Requirement::DIGITS], methods: 'DELETE')]
    public function deleteQueue(int $id): JsonResponse
    {
        $queue = $this->queueService->getQueueById($id);

        if ($queue === null) {
            throw new NotFoundHttpException('Queue not found.');
        }

        if ($this->queueService->hasIpAddresses($queue)) {
            throw new BadRequestHttpException('Unassign all IP addresses from the queue before deleting it.');
        }

        if ($this->queueService->hasSends($queue)) {
            throw new BadRequestHttpException('Unassign all IPs and wait until all sends are cleared up after the retention period before deleting.');
        }

        $this->queueService->deleteQueue($queue);

        return $this->json([]);
    }
}
