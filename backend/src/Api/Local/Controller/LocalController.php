<?php

namespace App\Api\Local\Controller;

use App\Api\Local\AllowPrivateNetwork;
use App\Api\Local\Input\IncomingBounceInput;
use App\Api\Local\Input\SendAttemptDoneInput;
use App\Service\Local\LocalService;
use App\Service\Send\SendService;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\ServerNotFoundException;
use Psr\Log\LoggerInterface;
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
        private SendService $sendService,
        private LocalService $localService,
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
        $dsn = $input->dsn;
        if (!$dsn) {
            // TODO: Handle error
        }

        $this->localService->handleIncomingBounce($input->bounce_uuid, $dsn);

        return new JsonResponse();
    }
}
