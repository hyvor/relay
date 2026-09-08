<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\Domain\DomainCreateInput;
use App\Api\Console\Input\Domain\DomainIdOrDomainInput;
use App\Api\Console\Object\DomainObject;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\DomainService;
use App\Service\Domain\DomainStatusService;
use App\Service\Domain\Exception\DkimVerificationFailedException;
use App\Service\Domain\Exception\DomainDeletionFailedException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

class DomainsController extends AbstractController
{
    public function __construct(
        private DomainService $domainService,
        private DomainStatusService $domainStatusService,
    ) {
    }

    #[Route('/domains', methods: 'GET')]
    #[ScopeRequired(Scope::DOMAINS_READ)]
    #[OA\Get(
        summary: 'Get all domains',
        description: 'Returns all domains registered for the project.'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of domains',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: DomainObject::class))
        )
    )]
    public function list(Project $project, Request $request): JsonResponse
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
    #[OA\Post(
        summary: 'Create a domain',
        description: 'Registers a new domain for the project. Generates DKIM keys by default unless a custom private key is provided.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the created domain object.',
        content: new Model(type: DomainObject::class)
    )]
    public function create(
        Project $project,
        #[MapRequestPayload] DomainCreateInput $createInput
    ): JsonResponse {
        if ($this->domainService->getDomainByProjectAndName($project, $createInput->domain)) {
            throw new BadRequestHttpException('Domain already exists');
        }

        $domain = $this->domainService->createDomain(
            $project,
            $createInput->domain,
            $createInput->dkim_selector,
            customDkimPrivateKey: $createInput->dkim_private_key,
        );

        return new JsonResponse(new DomainObject($domain));
    }

    #[Route('/domains/verify', methods: 'POST')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    #[OA\Post(
        summary: 'Verify a domain',
        description: 'Verifies the DKIM DNS records of a pending domain and updates its status.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the verified domain object.',
        content: new Model(type: DomainObject::class)
    )]
    public function verify(
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
    #[OA\Get(
        summary: 'Get a domain by ID or name',
        description: 'Returns a single domain identified by its ID or domain name.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the domain object.',
        content: new Model(type: DomainObject::class)
    )]
    public function get(
        Project $project,
        #[MapRequestPayload] DomainIdOrDomainInput $input
    ): JsonResponse {
        $domain = $input->validateAndGetDomain($project, $this->domainService);
        return new JsonResponse(new DomainObject($domain));
    }

    #[Route('/domains', methods: 'DELETE')]
    #[ScopeRequired(Scope::DOMAINS_WRITE)]
    #[OA\Delete(
        summary: 'Delete a domain',
        description: 'Permanently deletes a domain from the project.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns an empty response on success.',
        content: new OA\JsonContent()
    )]
    public function delete(
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
