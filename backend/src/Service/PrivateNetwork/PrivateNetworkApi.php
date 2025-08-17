<?php

namespace App\Service\PrivateNetwork;

use App\Entity\Server;
use App\Entity\Type\ServerTaskType;
use App\Service\PrivateNetwork\Exception\PrivateNetworkCallException;
use App\Service\Server\ServerTaskService;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrivateNetworkApi
{

    public function __construct(
        private ServerTaskService $serverTaskService,
    )
    {
    }

    public function callUpdateServerStateApi(Server $server): void
    {
        $this->serverTaskService->createTask(
            $server,
            ServerTaskType::UPDATE_STATE,
            []
        );
    }
}
