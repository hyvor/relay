<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Idempotency\IdempotencySupported;
use App\Api\Console\Input\SendEmail\SendEmailInput;
use App\Api\Console\Input\SendEmail\UnableToDecodeAttachmentBase64Exception;
use App\Api\Console\Object\SendObject;
use App\Api\Console\Resolver\ProjectResolver;
use App\Entity\Project;
use App\Entity\Send;
use App\Entity\Type\ProjectSendType;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Domain\DomainService;
use App\Service\Send\EmailAddressFormat;
use App\Service\Send\Exception\EmailTooLargeException;
use App\Service\Send\SendLimits;
use App\Service\Send\SendService;
use App\Service\Queue\QueueService;
use App\Service\SendAttempt\SendAttemptService;
use App\Service\SendFeedback\SendFeedbackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class SendController extends AbstractController
{
    public function __construct(
        private SendService $sendService,
        private SendAttemptService $sendAttemptService,
        private SendFeedbackService $sendFeedbackService,
        private DomainService $domainService,
        private QueueService $queueService,
    ) {
    }

    #[Route("/sends", methods: "POST")]
    #[ScopeRequired(Scope::SENDS_SEND)]
    #[IdempotencySupported]
    public function sendEmail(
        Project $project,
        #[MapRequestPayload] SendEmailInput $sendEmailInput
    ): JsonResponse {
        $fromAddress = $sendEmailInput->getFromAddress();

        $domainName = EmailAddressFormat::getDomainFromEmail($fromAddress->getAddress());
        $domain = $this->domainService->getDomainByProjectAndName(
            $project,
            $domainName
        );

        if ($domain === null) {
            throw new BadRequestException(
                "Domain $domainName is not registered for this project"
            );
        }

        if ($domain->getStatus()->canSendEmails() !== true) {
            throw new BadRequestException(
                "Domain $domainName is not allowed to send emails (status: {$domain->getStatus()->value})"
            );
        }

        $to = $sendEmailInput->getToAddresses();
        $cc = $sendEmailInput->getCcAddresses();
        $bcc = $sendEmailInput->getBccAddresses();

        $totalRecipientsCount = count($to) + count($cc) + count($bcc);
        $maxRecipients = SendLimits::MAX_RECIPIENTS_PER_SEND;
        if ($totalRecipientsCount > $maxRecipients) {
            throw new BadRequestException(
                "Total number of recipients (To, Cc, Bcc) exceeds the maximum allowed limit of $maxRecipients."
            );
        }

        $queue = $project->getSendType() === ProjectSendType::TRANSACTIONAL ?
            $this->queueService->getTransactionalQueue() :
            $this->queueService->getDistributionalQueue();
        assert($queue !== null, 'Transactional or Distributional should be set');

        try {
            $attachments = $sendEmailInput->getAttachments();
        } catch (UnableToDecodeAttachmentBase64Exception $exception) {
            throw new BadRequestException(
                "Base64 decoding of attachment failed: index $exception->attachmentIndex"
            );
        }

        try {
            $send = $this->sendService->createSend(
                $project,
                $domain,
                $queue,
                $fromAddress,
                $to,
                $cc,
                $bcc,
                $sendEmailInput->subject,
                $sendEmailInput->body_html,
                $sendEmailInput->body_text,
                $sendEmailInput->headers,
                $attachments
            );
        } catch (EmailTooLargeException) {
            throw new BadRequestException(
                "Email size exceeds the maximum allowed size of 10MB."
            );
        }

        return new JsonResponse([
            'id' => $send->getId(),
            'message_id' => $send->getMessageId(),
        ]);
    }

    #[Route("/sends", methods: "GET")]
    #[ScopeRequired(Scope::SENDS_READ)]
    public function getSends(Request $request, Project $project): JsonResponse
    {
        $limit = $request->query->getInt("limit", 50);
        $offset = $request->query->getInt("offset", 0);

        $status = null;
        if ($request->query->has("status")) {
            $status = SendRecipientStatus::tryFrom($request->query->getString("status"));
        }

        $fromSearch = null;
        if ($request->query->has("from_search")) {
            $fromSearch = $request->query->getString("from_search");
        }

        $toSearch = null;
        if ($request->query->has("to_search")) {
            $toSearch = $request->query->getString("to_search");
        }

        $subjectSearch = null;
        if ($request->query->has("subject_search")) {
            $subjectSearch = $request->query->getString("subject_search");
        }

        $sends = $this->sendService
            ->getSends(
                $project,
                $status,
                $fromSearch,
                $toSearch,
                $subjectSearch,
                $limit,
                $offset
            )
            ->map(fn($send) => new SendObject($send));

        return $this->json($sends);
    }

    #[Route("/sends/{id}", methods: "GET")]
    #[ScopeRequired(Scope::SENDS_READ)]
    public function getById(Send $send): JsonResponse
    {
        $attempts = $this->sendAttemptService->getSendAttemptsOfSend($send);
        $feedback = $this->sendFeedbackService->getFeedbackOfSend($send);

        return $this->json(
            new SendObject(
                $send,
                attempts: $attempts,
                feedback: $feedback,
                content: true
            )
        );
    }

    #[Route("/sends/uuid/{uuid}", requirements: ['uuid' => Requirement::UUID], methods: "GET")]
    #[ScopeRequired(Scope::SENDS_READ)]
    public function getByUuid(Project $project, string $uuid): JsonResponse
    {
        $send = $this->sendService->getSendByUuid($uuid);

        if ($send === null) {
            throw new NotFoundHttpException("Send with UUID $uuid not found");
        }

        if ($send->getProject()->getId() !== $project->getId()) {
            throw new BadRequestException(
                "Send with UUID $uuid does not belong to project"
            );
        }

        $attempts = $this->sendAttemptService->getSendAttemptsOfSend($send);
        $feedback = $this->sendFeedbackService->getFeedbackOfSend($send);

        return $this->json(
            new SendObject(
                $send,
                attempts: $attempts,
                feedback: $feedback,
                content: true
            )
        );
    }

}
