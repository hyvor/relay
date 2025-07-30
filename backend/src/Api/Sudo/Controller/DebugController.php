<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\Debug\ParseBounceOrFblInput;
use App\Api\Sudo\Object\DebugIncomingEmailObject;
use App\Service\Debug\DebugIncomingMailService;
use App\Service\PrivateNetwork\Exception\GoHttpCallException;
use App\Service\PrivateNetwork\GoHttpApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends AbstractController
{

    public function __construct(
        private GoHttpApi $goHttpApi,
        private DebugIncomingMailService $debugIncomingMailService
    )
    {
    }

    #[Route('/debug/incoming-mails', methods: 'GET')]
    public function getDebugIncomingMails(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $offset = $request->query->getInt('offset', 0);

        $mails = $this->debugIncomingMailService->getIncomingMails($limit, $offset);

        return new JsonResponse(
            array_map(fn ($mail) => new DebugIncomingEmailObject($mail), $mails),
        );
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