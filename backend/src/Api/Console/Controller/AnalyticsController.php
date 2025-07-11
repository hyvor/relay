<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AnalyticsController extends AbstractController
{

    // gets:
    // - sends count last 30 days
    // - bounce rate (last 30 days)
    // - complaint rate (last 30 days)
    #[Route('/analytics/stats', methods: 'GET')]
    #[ScopeRequired(Scope::ANALYTICS_READ)]
    public function getStats(): JsonResponse
    {


    }

}