<?php

namespace App\Api\Console\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class EmailController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{

    #[Route('/email', methods: 'GET')]
    public function email(): JsonResponse
    {
        //

        return new JsonResponse([]);

    }

}