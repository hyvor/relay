<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\Domain\DomainIdOrDomainInput;
use App\Api\Console\Input\Domain\DomainCreateInput;
use App\Api\Console\Object\DomainObject;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\DomainService;
use App\Service\Domain\DomainStatusService;
use App\Service\Domain\Exception\DkimVerificationFailedException;
use App\Service\Domain\Exception\DomainDeletionFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

class DomainController extends AbstractController
{

    public function __construct(
        private DomainService $domainService,
        private DomainStatusService $domainStatusService,
    ) {
    }

    #[Route('/domains', methods: 'GET')]
    #[ScopeRequired(Scope::DOMAINS_READ)]
    public function getDomains(Project $project, Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 50);
        $offset = $request->query->getInt('offset', 0);

        $search = null;
        if ($request->query->has('search')) {
            $search = $request->query->getString('search');
        }

        $domains = $this->domainService->getProjectDomains(
            $project,
            $search,
            $limit,
            $offset
        )->map(fn(Domain $domain) => new DomainObject($domain));

        return $this->json($domains);
    }

    #[Route('/domains', methods: 'POST')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    public function createDomain(
        Project $project,
        #[MapRequestPayload] DomainCreateInput $createInput
    ): JsonResponse {
        if ($this->domainService->getDomainByProjectAndName($project, $createInput->domain)) {
            throw new BadRequestHttpException('Domain already exists');
        }

        $domain = $this->domainService->createDomain(
            $project,
            $createInput->domain
        );

        return new JsonResponse(new DomainObject($domain));
    }

    #[Route('/domains/verify', methods: 'POST')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    public function verifyDomain(
        Project $project,
        #[MapRequestPayload] DomainIdOrDomainInput $input
    ): JsonResponse {
        $domain = $input->validateAndGetDomain($project, $this->domainService);

        if ($domain->getStatus() !== DomainStatus::PENDING) {
            throw new BadRequestHttpException('You can only verify a domain that is in PENDING status.');
        }

        try {
            $this->domainStatusService->updateAfterDkimVerification($domain, flush: true);
        } catch (DkimVerificationFailedException $e) {
            throw new HttpException(500, 'DKIM verification failed due an internal error: ' . $e->getMessage(), $e);
        }

        return new JsonResponse(new DomainObject($domain));
    }

    #[Route('/domains/by', methods: 'GET')]
    #[ScopeRequired(Scope::DOMAINS_READ)]
    public function getDomainById(
        Project $project,
        #[MapRequestPayload] DomainIdOrDomainInput $input
    ): JsonResponse {
        $domain = $input->validateAndGetDomain($project, $this->domainService);
        return new JsonResponse(new DomainObject($domain));
    }


    #[Route('/domains', methods: 'DELETE')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    public function deleteDomain(
        Project $project,
        #[MapRequestPayload] DomainIdOrDomainInput $input
    ): JsonResponse {
        $domain = $input->validateAndGetDomain($project, $this->domainService);

        try {
            $this->domainService->deleteDomain($domain);
        } catch (DomainDeletionFailedException $e) {
            throw new BadRequestHttpException('Domain deletion failed: ' . $e->getMessage(), previous: $e);
        }

        return new JsonResponse();
    }
}
