<?php

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class EmailController
{

    #[Route('/email', methods: 'GET')]
    public function email(): JsonResponse
    {

        //


        return new JsonResponse([]);

    }

}