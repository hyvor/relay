<?php

namespace App\Api\Console\Controller\Org;

use App\Api\Console\Input\Kyc\KycSubmitInput;
use App\Api\Console\Object\KycObject;
use App\Service\Kyc\Countries;
use App\Service\Kyc\Exception\KycAlreadyApprovedException;
use App\Service\Kyc\Exception\PaymentMethodRequiredException;
use App\Service\Kyc\KycService;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\OrgEndpoint;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class KycController extends AbstractController
{
    public function __construct(
        private KycService $kycService,
        private InternalConfig $internalConfig,
    ) {
    }

    #[Route('/kyc', methods: 'GET')]
    #[OrgEndpoint]
    public function get(
        Request $request,
        ConsoleAuthResults $consoleAuth,
    ): JsonResponse
    {
        $this->assertCloud();

        $organizationId = $consoleAuth->getOrganizationId();
        $kyc = $this->kycService->getCurrentByOrganizationId($organizationId);

        return $this->json($kyc ? new KycObject($kyc) : null);
    }

    #[Route('/kyc/countries', methods: 'GET')]
    #[OrgEndpoint]
    public function countries(): JsonResponse
    {
        $this->assertCloud();

        return $this->json(Countries::names());
    }

    #[Route('/kyc', methods: 'POST')]
    #[OrgEndpoint]
    public function submit(
        Request $request,
        ConsoleAuthResults $consoleAuth,
        #[MapRequestPayload] KycSubmitInput $input
    ): JsonResponse {
        $this->assertCloud();

        $organizationId = $consoleAuth->getOrganizationId();

        try {
            $kyc = $this->kycService->submit(
                $organizationId,
                $input->account_type,
                $input->name,
                $input->country,
                $input->address,
                $input->website,
                $input->email,
                $input->content_ownership,
                $input->sending_transactional,
                $input->sending_distributional,
                $input->use_case,
            );
        } catch (KycAlreadyApprovedException | PaymentMethodRequiredException $e) {
            throw new BadRequestHttpException($e->getMessage(), previous: $e);
        }

        return $this->json(new KycObject($kyc));
    }

    private function assertCloud(): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            throw new NotFoundHttpException('KYC is only available on cloud deployments.');
        }
    }
}
