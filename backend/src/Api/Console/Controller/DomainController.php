<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\Domain\DeleteDomainInput;
use App\Api\Console\Input\Domain\DomainCreateInput;
use App\Api\Console\Object\DomainObject;
use App\Entity\Domain;
use App\Entity\Project;
use App\Service\Domain\DomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class DomainController extends AbstractController
{

    public function __construct(private DomainService $domainService)
    {
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

    #[Route('/domains/{id}/verify', methods: 'POST')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    public function verifyDomain(Domain $domain): JsonResponse
    {
        if ($domain->getDkimVerified()) {
            throw new BadRequestException('Domain is already verified');
        }

        $this->domainService->verifyDkimAndUpdate($domain);
        return new JsonResponse(new DomainObject($domain));
    }

    #[Route('/domains', methods: 'DELETE')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    public function deleteDomain(
        Project $project,
        #[MapRequestPayload] DeleteDomainInput $input
    ): JsonResponse
    {
        if ($input->id) {
            $domain = $this->domainService->getDomainById($input->id);
        } else {
            assert(is_string($input->domain));
            $domain = $this->domainService->getDomainByProjectAndName($project, $input->domain);
        }

        if (!$domain) {
            throw new BadRequestException('Domain not found');
        }

        if ($domain->getProject() !== $project) {
            throw new BadRequestException('Domain does not belong to the project');
        }

        $this->domainService->deleteDomain($domain);
        return new JsonResponse([]);
    }

}
