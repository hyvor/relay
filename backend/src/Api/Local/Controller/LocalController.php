<?php

namespace App\Api\Local\Controller;

use App\Api\Console\Metric\MetricsListener;
use App\Api\Local\Input\IncomingInput;
use App\Api\Local\Input\IncomingType;
use App\Api\Local\Input\SendAttemptDoneInput;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use App\Service\DebugIncomingEmail\DebugIncomingEmailService;
use App\Service\IncomingMail\IncomingMailService;
use App\Service\Send\SendService;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\ServerNotFoundException;
use App\Service\SendAttempt\SendAttemptService;
use App\Service\Tls\Acme\AcmeClient;
use Prometheus\RenderTextFormat;
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
        private SendAttemptService $sendAttemptService,
        private IncomingMailService $incomingMailService,
        private DebugIncomingEmailService $debugIncomingEmailService,
        private MetricsListener $metricsListener,
        private AcmeClient $client
    ) {
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
    ): JsonResponse {
        foreach ($input->send_attempt_ids as $id) {
            $sendAttempt = $this->sendAttemptService->getSendAttemptById($id);

            if ($sendAttempt === null) {
                continue;
            }

            $this->sendAttemptService->handleAfterSendAttempt($sendAttempt);
        }

        return new JsonResponse([]);
    }

    #[Route('/incoming', methods: 'POST')]
    public function incoming(
        #[MapRequestPayload] IncomingInput $input
    ): JsonResponse {
        $isBounce = $input->type === IncomingType::BOUNCE;
        $debugIncomingEmailStatus = $input->error ? DebugIncomingEmailStatus::FAILED : DebugIncomingEmailStatus::SUCCESS;

        $debugIncomingEmail = $this->debugIncomingEmailService->createDebugIncomingEmail(
            $isBounce ? DebugIncomingEmailType::BOUNCE : DebugIncomingEmailType::COMPLAINT,
            $debugIncomingEmailStatus,
            $input->raw_email,
            $input->mail_from,
            $input->rcpt_to,
            $isBounce ? (array)$input->dsn : (array)$input->arf,
            $input->error
        );

        if ($input->error) {
            return new JsonResponse();
        }

        if ($isBounce) {
            assert($input->bounce_uuid !== null);
            assert($input->dsn !== null);

            $this->incomingMailService->handleIncomingBounce(
                $input->bounce_uuid,
                $input->dsn,
                $debugIncomingEmail
            );
        } else {
            assert($input->arf !== null);

            $this->incomingMailService->handleIncomingComplaint(
                $input->arf,
                $debugIncomingEmail
            );
        }

        return new JsonResponse();
    }

    #[Route('/metrics', methods: 'GET')]
    public function exportPrometheusMetrics(): JsonResponse
    {
        $renderer = new RenderTextFormat();
        return new JsonResponse(
            [
                "metrics" => $renderer->render($this->metricsListener->getSamples())
            ],
            headers: [
                'Content-Type' => RenderTextFormat::MIME_TYPE
            ]
        );
    }
}
