<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\SendEmailInput;
use App\Entity\Project;
use App\Service\Domain\DomainService;
use App\Service\Email\EmailAddressFormat;
use App\Service\Email\SendService;
use App\Service\Queue\QueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
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

    #[Route('/email', methods: 'POST')]
    public function sendTransactionalEmail(
        Project $project,
        #[MapRequestPayload] SendEmailInput $sendEmailInput
    ): JsonResponse
    {

        $fromAddress = $sendEmailInput->getFromAddress();

        $domainName = EmailAddressFormat::getDomainFromEmail($fromAddress->getAddress());
        $domain = $this->domainService->getDomainByName($domainName);

        if ($domain === null) {
            throw new BadRequestException("Domain $domainName not found for email address " . $fromAddress->getAddress());
        }

        $queue = $this->queueService->getTransactionalQueue();
        assert($queue !== null, 'Transactional queue not found');

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