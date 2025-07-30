<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\Debug\ParseBounceOrFblInput;
use App\Service\PrivateNetwork\Exception\GoHttpCallException;
use App\Service\PrivateNetwork\GoHttpApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends AbstractController
{

    public function __construct(
        private GoHttpApi $goHttpApi,
    )
    {
    }

    #[Route('/debug/parse-bounce-fbl', methods: 'POST')]
    public function parseBounceOrFbl(
        #[MapRequestPayload] ParseBounceOrFblInput $input
    ): JsonResponse
    {

        try {
            $parsed = $this->goHttpApi->parseBounceOrFbl(
                $input->raw,
                $input->type
            );
        } catch (GoHttpCallException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        return $this->json([
            'parsed' => $parsed,
        ]);

    }

}