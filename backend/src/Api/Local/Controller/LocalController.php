<?php

namespace App\Api\Local\Controller;

use App\Api\Local\Input\SendAttemptDoneInput;
use App\Api\Local\Input\SendDoneInput;
use App\Entity\Type\SendStatus;
use App\Service\Send\Dto\SendUpdateDto;
use App\Service\Send\SendService;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\ServerNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class LocalController extends AbstractController
{

    use ClockAwareTrait;

    public function __construct(
        private SendService $sendService
    )
    {
    }

    #[Route('/state', methods: 'GET')]
    public function getState(GoStateFactory $goStateFactory): JsonResponse
    {
        try {
            $state = $goStateFactory->create();
        } catch (ServerNotFoundException $e) {
            throw new UnprocessableEntityHttpException('Server not yet initialized', $e);
        }

        return new JsonResponse($state);
    }

    #[Route('/send-attempt/done', methods: 'POST')]
    public function sendAttemptDone(
        #[MapRequestPayload] SendAttemptDoneInput $input,
    ): JsonResponse
    {

        foreach ($input->send_attempt_ids as $id) {
            $sendAttempt = $this->sendService->getSendAttemptById($id);

            if ($sendAttempt === null) {
                continue;
            }

            $this->sendService->dispatchSendAttemptCreatedEvent($sendAttempt);
        }

        return new JsonResponse([]);
    }

    #[Route('/send/done', methods: 'POST')]
    public function sendDone(
        #[MapRequestPayload] SendDoneInput $input,
        Request $request
    ): JsonResponse
    {
        $send = $this->sendService->getSendById($input->sendId);

        if ($send === null) {
            throw new UnprocessableEntityHttpException('Send not found');
        }

        $update = new SendUpdateDto();
        $status = $input->getStatusEnum();

        $update->status = $status;
        $update->result = $input->result;

        if ($status === SendStatus::ACCEPTED) {
            $update->sentAt = $this->now();
        } else {
            $update->failedAt = $this->now();
        }

        $this->sendService->updateSend($send, $update);

        return new JsonResponse([]);
    }

}