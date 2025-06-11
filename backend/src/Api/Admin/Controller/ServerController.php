<?php

namespace App\Api\Admin\Controller;

use App\Service\Server\ServerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ServerController extends AbstractController
{

    public function __construct(
        private ServerService $serverService
    )
    {
    }

    #[Route('/servers', methods: 'GET')]
    public function getServers(): JsonResponse
    {



    }

}