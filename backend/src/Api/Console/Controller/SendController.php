<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\SendEmailInput;
use App\Api\Console\Object\SendObject;
use App\Entity\Project;
use App\Entity\Type\SendStatus;
use App\Service\Domain\DomainService;
use App\Service\Email\EmailAddressFormat;
use App\Service\Email\SendService;
use App\Service\Queue\QueueService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class SendController extends AbstractController
{
    public function __construct(
        private SendService $sendService,
        private DomainService $domainService,
        private QueueService $queueService
    ) {}

    #[Route("/emails", methods: "GET")]
    public function getEmails(Request $request, Project $project): JsonResponse
    {
        $limit = $request->query->getInt("limit", 50);
        $offset = $request->query->getInt("offset", 0);

        $status = null;
        if ($request->query->has("status")) {
            $status = SendStatus::tryFrom($request->query->getString("status"));
        }

        $fromSearch = null;
        if ($request->query->has("from_search")) {
            $fromSearch = $request->query->getString("from_search");
        }

        $toSearch = null;
        if ($request->query->has("to_search")) {
            $toSearch = $request->query->getString("to_search");
        }

        $sends = $this->sendService
            ->getSends(
                $project,
                $status,
                $fromSearch,
                $toSearch,
                $limit,
                $offset
            )
            ->map(fn($send) => new SendObject($send));

        return $this->json($sends);
    }

    #[Route("/emails/uuid/{uuid}", methods: "GET")]
    public function getByUuid(Project $project, string $uuid): JsonResponse
    {
        $send = $this->sendService->getSendByUuid($uuid);
        if ($send === null) {
            throw new BadRequestException("Send with UUID $uuid not found");
        }

        if ($send->getProject()->getId() !== $project->getId()) {
            throw new BadRequestException(
                "Send with UUID $uuid does not belong to project " .
                    $project->getName()
            );
        }

        $attempts = $this->sendService->getSendAttemptsOfSend($send);

        return $this->json(new SendObject($send, $attempts));
    }

    #[Route("/sends", methods: "POST")]
    public function sendEmail(
        Project $project,
        #[MapRequestPayload] SendEmailInput $sendEmailInput
    ): JsonResponse {
        $fromAddress = $sendEmailInput->getFromAddress();

        $domainName = EmailAddressFormat::getDomainFromEmail(
            $fromAddress->getAddress()
        );
        $domain = $this->domainService->getDomainByProjectAndName(
            $project,
            $domainName
        );

        if ($domain === null) {
            throw new BadRequestException(
                "Domain $domainName not found for email address " .
                    $fromAddress->getAddress()
            );
        }

        $queue = $this->queueService->getTransactionalQueue();
        assert($queue !== null, "Transactional queue not found");

        $this->sendService->createSend(
            $project,
            $domain,
            $queue,
            $fromAddress,
            $sendEmailInput->getToAddress(),
            $sendEmailInput->subject,
            $sendEmailInput->body_html,
            $sendEmailInput->body_text
        );

        return new JsonResponse([]);
    }
}
