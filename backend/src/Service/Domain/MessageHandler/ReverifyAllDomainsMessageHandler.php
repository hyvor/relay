<?php

namespace App\Service\Domain\MessageHandler;

use App\Service\Domain\Message\ReverifyAllDomainsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reverifies all verified and warning domains to make sure they are still valid.
 *
 * If not valid,
 * verified -> warning
 * warning -> pending
 */
#[AsMessageHandler]
class ReverifyAllDomainsMessageHandler
{

    public function __invoke(ReverifyAllDomainsMessage $message): void
    {
        //
    }

}