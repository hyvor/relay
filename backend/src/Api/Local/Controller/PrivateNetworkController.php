<?php

namespace App\Api\Local\Controller;

use App\Api\Local\AllowPrivateNetwork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PrivateNetworkController extends AbstractController
{

    /**
     * This endpoint is called when the server state is updated.
     * It signals Go to update its state.
     */
    #[Route('/state/update', methods: 'POST')]
    #[AllowPrivateNetwork]
    public function updateSettings() : JsonResponse
    {

        return new JsonResponse([
            'message' => 'Settings updated successfully',
        ]);

    }

}