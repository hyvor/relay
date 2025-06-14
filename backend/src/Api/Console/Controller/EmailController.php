<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\SendEmailInput;
use App\Entity\Project;
use App\Service\Domain\DomainService;
use App\Service\Email\SendService;
use App\Service\Queue\QueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class EmailController extends AbstractController
{

    public function __construct(
        private SendService $sendService,
        private DomainService $domainService,
        private QueueService $queueService
    )
    {
    }

    #[Route('/email/transactional', methods: 'POST')]
    public function sendTransactionalEmail(
        Project $project,
        #[MapRequestPayload] SendEmailInput $sendEmailInput
    ): JsonResponse
    {

        $fromAddress = $sendEmailInput->from;
        $domainName = explode('@', $fromAddress)[1] ?? null;
        assert($domainName !== null);
        $domain = $this->domainService->getDomainByName($domainName);
        assert($domain !== null);

        $queue = $this->queueService->getTransactionalQueue();
        assert($queue !== null, 'Transactional queue not found');

        $this->sendService->createSend(
            $project,
            $domain,
            $queue,
            $fromAddress,
            $sendEmailInput->to,
            $sendEmailInput->subject,
            $sendEmailInput->body_html,
            $sendEmailInput->body_text
        );

        return new JsonResponse([]);

    }

}