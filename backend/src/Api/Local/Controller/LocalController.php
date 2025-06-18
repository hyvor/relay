<?php

namespace App\Api\Local\Controller;

use App\Api\Local\Input\SendDoneInput;
use App\Entity\Type\SendStatus;
use App\Service\Email\Dto\SendUpdateDto;
use App\Service\Email\SendService;
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

        if ($status === SendStatus::SENT) {
            $update->sentAt = $this->now();
        } else {
            $update->failedAt = $this->now();
        }

        $this->sendService->updateSend($send, $update);

        return new JsonResponse([]);
    }

}