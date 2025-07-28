<?php

namespace App\Api\Local\Controller;

use App\Api\Local\AllowPrivateNetwork;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\ServerNotFoundException;
use App\Service\PrivateNetwork\Exception\GoHttpCallException;
use App\Service\PrivateNetwork\GoHttpApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PrivateNetworkController extends AbstractController
{

    public function __construct(
        private GoHttpApi $goHttpApi,
        private GoStateFactory $goStateFactory
    )
    {
    }

    /**
     * This endpoint is called when the server state is updated.
     * It signals Go to update its state.
     */
    #[Route('/state/update', methods: 'POST')]
    #[AllowPrivateNetwork]
    public function updateState() : JsonResponse
    {
        try {
            $this->goHttpApi->updateState($this->goStateFactory->create());
        } catch (GoHttpCallException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (ServerNotFoundException $e) {
            throw new BadRequestHttpException('Server not yet initialized', $e);
        }

        return new JsonResponse([
            'message' => 'State updated successfully',
        ]);
    }

}