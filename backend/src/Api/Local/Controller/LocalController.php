<?php

namespace App\Api\Local\Controller;

use App\Api\Local\Input\IncomingBounceInput;
use App\Api\Local\Input\IncomingFblInput;
use App\Api\Local\Input\SendAttemptDoneInput;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use App\Service\DebugIncomingEmail\DebugIncomingEmailService;
use App\Service\IncomingMail\IncomingMailService;
use App\Service\Send\SendService;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\ServerNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class LocalController extends AbstractController
{

    use ClockAwareTrait;

    public function __construct(
        private SendService         $sendService,
        private IncomingMailService $incomingMailService,
        private DebugIncomingEmailService $debugIncomingEmailService,
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

    #[Route('/send-attempts/done', methods: 'POST')]
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

    #[Route('/incoming/bounce', methods: 'POST')]
    public function incomingBounce(
        #[MapRequestPayload] IncomingBounceInput $input
    ): JsonResponse
    {
        if (!$input->dsn) {
            $debugIncomingEmailStatus = DebugIncomingEmailStatus::FAILED;
        }

        else {
            $this->incomingMailService->handleIncomingBounce($input->bounce_uuid, $input->dsn);
            $debugIncomingEmailStatus = DebugIncomingEmailStatus::SUCCESS;
        }

        $this->debugIncomingEmailService->createDebugIncomingEmail(
            DebugIncomingEmailType::BOUNCE,
            $debugIncomingEmailStatus,
            $input->raw_email,
            $input->mail_from,
            $input->rcpt_to,
            (array)$input->dsn,
            $input->error
        );

        return new JsonResponse();
    }

    #[Route('/incoming/fbl', methods: 'POST')]
    public function incomingFbl(
        #[MapRequestPayload] IncomingFblInput $input
    ): JsonResponse
    {
        if (!$input->arf) {
            $debugIncomingEmailStatus = DebugIncomingEmailStatus::FAILED;
        } else {
            $this->incomingMailService->handleIncomingFbl($input->arf);
            $debugIncomingEmailStatus = DebugIncomingEmailStatus::SUCCESS;
        }

        $this->debugIncomingEmailService->createDebugIncomingEmail(
            DebugIncomingEmailType::FBL,
            $debugIncomingEmailStatus,
            $input->raw_email,
            $input->mail_from,
            $input->rcpt_to,
            (array)$input->arf,
            $input->error
        );

        $this->debugIncomingEmailService->createDebugIncomingEmail(
            DebugIncomingEmailType::FBL,
            $debugIncomingEmailStatus,
            $input->raw_email,
            $input->mail_from,
            $input->rcpt_to,
            (array)$input->arf,
            $input->error
        );

        return new JsonResponse();
    }
}
