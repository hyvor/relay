<?php

namespace App\Api\Console\Controller\Org;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\OrganizationLevelEndpoint;
use App\Api\Console\Input\Kyc\KycSubmitInput;
use App\Api\Console\Object\KycObject;
use App\Service\Kyc\Exception\KycAlreadyApprovedException;
use App\Service\Kyc\Exception\PaymentMethodRequiredException;
use App\Service\Kyc\KycService;
use Hyvor\Internal\InternalConfig;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OrganizationLevelEndpoint]
    #[OA\Get(
        summary: 'Get KYC',
        description: 'Returns the KYC submission for the current organization, or null if none exists. ' .
            'Only available on cloud deployments.'
    )]
    #[OA\Response(
        response: 200,
        description: 'The KYC submission, or null if the organization has not submitted one yet.',
        content: new Model(type: KycObject::class)
    )]
    public function get(Request $request): JsonResponse
    {
        $this->assertCloud();

        $organization = AuthorizationListener::getOrganization($request);
        $kyc = $this->kycService->getByOrganizationId($organization->id);

        return $this->json($kyc ? new KycObject($kyc) : null);
    }

    #[Route('/kyc', methods: 'POST')]
    #[OrganizationLevelEndpoint]
    #[OA\Post(
        summary: 'Submit KYC',
        description: 'Submits (or resubmits, if not yet approved) the KYC data for the current organization. ' .
            'Only available on cloud deployments.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the saved KYC submission.',
        content: new Model(type: KycObject::class)
    )]
    public function submit(
        Request $request,
        #[MapRequestPayload] KycSubmitInput $input
    ): JsonResponse {
        $this->assertCloud();

        $organization = AuthorizationListener::getOrganization($request);

        try {
            $kyc = $this->kycService->submit(
                $organization->id,
                $input->full_name,
                $input->business_type,
                $input->business_name,
                $input->country,
                $input->address,
                $input->phone,
                $input->website,
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
