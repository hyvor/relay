<?php

namespace App\Service\Domain\MessageHandler;

use App\Service\Domain\DomainService;
use App\Service\Domain\Message\DkimVerifyMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DkimVerifyMessageHandler
{

    public function __construct(
        private DomainService $domainService
    )
    {
    }

    public function __invoke(DkimVerifyMessage $message): void
    {

        $domain = $this->domainService->getDomainById($message->domainId);

        if ($domain === null) {
            return;
        }


    }

}