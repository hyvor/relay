<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\SendEmailInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class EmailController extends AbstractController
{

    #[Route('/email', methods: 'POST')]
    public function email(
        #[MapRequestPayload] SendEmailInput $sendEmailInput
    ): JsonResponse
    {
        //

        return new JsonResponse([]);

    }

}