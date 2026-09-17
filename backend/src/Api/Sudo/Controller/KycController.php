<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\Kyc\GetKycsInput;
use App\Api\Sudo\Object\KycObject;
use App\Api\Sudo\Object\OrganizationObject;
use App\Entity\Kyc;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\Exception\KycNotPendingException;
use App\Service\Kyc\KycService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Auth\Dto\Organization;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class KycController extends AbstractController
{
    public function __construct(
        private KycService $kycService,
        private InternalConfig $internalConfig,
        private AuthInterface $auth,
    ) {
    }

    #[Route('/kyc', methods: 'GET')]
    public function list(#[MapQueryString] GetKycsInput $input): JsonResponse
    {
        $this->assertCloud();

        $status = $input->status !== null ? KycStatus::from($input->status) : null;

        $kycs = $this->kycService->listAll(
            $status,
            $input->organization_id,
            $input->sort_by,
            $input->sort,
            $input->limit,
            $input->offset,
        );

        $organizations = $this->resolveOrganizations($kycs);

        return $this->json([
            'kycs' => array_map(fn(Kyc $kyc) => new KycObject($kyc), $kycs),
            'orgs' => array_values(
                array_map(
                    fn(Organization $org) => new OrganizationObject($org),
                    $organizations,
                ),
            ),
            'total' => $this->kycService->countAll($status, $input->organization_id),
        ]);
    }

    #[Route('/kyc/{id}/approve', requirements: ['id' => Requirement::DIGITS], methods: 'POST')]
    public function approve(int $id): JsonResponse
    {
        $this->assertCloud();
        $kyc = $this->getKycOr404($id);

        try {
            $result = $this->kycService->approve($kyc);
        } catch (KycNotPendingException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->json([
            'kyc' => new KycObject($result->kyc),
            'charge_success' => $result->chargeSuccess,
            'charge_error' => $result->chargeError,
        ]);
    }

    #[Route('/kyc/{id}/reject', requirements: ['id' => Requirement::DIGITS], methods: 'POST')]
    public function reject(int $id): JsonResponse
    {
        $this->assertCloud();
        $kyc = $this->getKycOr404($id);

        try {
            $kyc = $this->kycService->reject($kyc);
        } catch (KycNotPendingException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->json([
            'kyc' => new KycObject($kyc),
        ]);
    }

    private function getKycOr404(int $id): Kyc
    {
        $kyc = $this->kycService->getById($id);

        if ($kyc === null) {
            throw new NotFoundHttpException("KYC $id not found");
        }

        return $kyc;
    }

    /**
     * @param Kyc[] $kycs
     * @return array<int, Organization>
     */
    private function resolveOrganizations(array $kycs): array
    {
        $orgIds = [];
        foreach ($kycs as $kyc) {
            $orgIds[$kyc->getOrganizationId()] = $kyc->getOrganizationId();
        }

        if ($orgIds === []) {
            return [];
        }

        return $this->auth->organizations(array_values($orgIds));
    }

    private function assertCloud(): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            throw new NotFoundHttpException('KYC is only available on cloud deployments.');
        }
    }
}
