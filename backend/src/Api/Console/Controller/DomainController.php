<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\DomainCreateInput;
use App\Api\Console\Object\DomainObject;
use App\Entity\Project;
use App\Service\Domain\DomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class DomainController extends AbstractController
{

    public function __construct(private DomainService $domainService)
    {
    }

    #[Route('/domains', methods: 'POST')]
    public function createDomain(
        Project $project,
        #[MapRequestPayload] DomainCreateInput $createInput
    ): JsonResponse
    {

        if ($this->domainService->getDomainByProjectAndName($project, $createInput->domain)) {
            throw new BadRequestException('Domain already exists');
        }

        $domain = $this->domainService->createDomain(
            $project,
            $createInput->domain
        );

        return new JsonResponse(new DomainObject($domain));

    }

}