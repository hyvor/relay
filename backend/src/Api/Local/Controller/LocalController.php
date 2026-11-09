<?php

namespace App\Api\Local\Controller;

use App\Api\Console\Metric\MetricsListener;
use App\Api\Local\Input\IncomingInput;
use App\Api\Local\Input\IncomingType;
use App\Api\Local\Input\SendAttemptDoneInput;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use App\Service\App\Config;
use App\Service\DebugIncomingEmail\DebugIncomingEmailService;
use App\Service\IncomingMail\IncomingMailService;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\ServerNotFoundException;
use App\Service\SendAttempt\SendAttemptService;
use App\Service\Send\SendContentStorage;
use App\Tests\Factory\KycFactory;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class LocalController extends AbstractController
{

    use ClockAwareTrait;

    public function __construct(
        private SendAttemptService $sendAttemptService,
        private IncomingMailService $incomingMailService,
        private DebugIncomingEmailService $debugIncomingEmailService,
        private MetricsListener $metricsListener,
        private SendContentStorage $sendContentStorage,
        private \Twig\Environment $twig,
        private Config $config,
    ) {
    }

    #[Route('/sends/{uuid}/raw', requirements: ['uuid' => Requirement::UUID], methods: 'GET')]
    public function getSendRawContent(string $uuid): StreamedResponse
    {
        $stream = $this->sendContentStorage->getRawStream($uuid);

        if ($stream === null) {
            throw new NotFoundHttpException("Raw content for send with UUID $uuid not found");
        }

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', 'message/rfc822');

        return $response;
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

    /**
     * I am keeping this in case the you need to render the KYC email template
     * when reviewing the PR. Make sure to remove this before merging.
     */
    #[Route('/render-template', methods: 'GET')]
    public function renderTemplate(Request $request): Response
    {
        $kyc = KycFactory::createOne();
        $kyc->setRejectReason('Incomplete documentation');

        $strings = [
            'name' => $kyc->getName(),
            'body_secondary' => null,
        ];
        $rejectReason = null;
        $billingUrl = null;

        switch ($request->query->getString('type', 'submitted')) {
            case 'rejected':
                $strings['subject'] = 'Your KYC submission was rejected';
                $strings['body_main'] = "We're sorry to inform you that your KYC submission for Hyvor Relay has been rejected.";
                $strings['body_secondary'] = 'Please review and update your details, then resubmit your KYC for another review.';
                $rejectReason = $kyc->getRejectReason();
                break;
            case 'approved':
                $strings['subject'] = 'Your KYC has been approved';
                $strings['body_main'] = 'Good news! Your KYC verification for Hyvor Relay has been approved.';
                $strings['body_secondary'] = "We couldn't automatically start a subscription for your organization. Please start a subscription before you can send emails.";
                $billingUrl = $this->config->getWebUrl() . '/console/billing';
                break;
            default:
                $strings['subject'] = 'Your KYC submission has been received';
                $strings['body_main'] = "We've received your KYC submission for Hyvor Relay. Our team will review your details and get back. This process may take upto 24 business hours.";
        }

        $html = $this->twig->render('mail/kyc.twig', [
            'component' => \Hyvor\Internal\Component\Component::RELAY->value,
            'strings' => $strings,
            'reject_reason' => $rejectReason,
            'billing_url' => $billingUrl,
        ]);

        return new Response($html);
    }

}
