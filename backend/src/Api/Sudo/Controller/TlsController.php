<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\TlsCertificateObject;
use App\Entity\Type\TlsCertificateType;
use App\Service\Instance\InstanceService;
use App\Service\Tls\TlsCertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TlsController extends AbstractController
{

    public function __construct(
        private InstanceService $instanceService,
        private TlsCertificateService $tlsCertificateService,
    ) {
    }

    #[Route('/tls/mail-certs', methods: 'GET')]
    public function getMailTlsCertificates(): JsonResponse
    {
        $instance = $this->instanceService->getInstance();

        $currentCert = null;
        $latestCert = null;

        if ($instance->getMailTlsCertificateId()) {
            $currentCert = $this->tlsCertificateService->getCertificateById($instance->getMailTlsCertificateId());
        }

        $latest = $this->tlsCertificateService->getLatestCertificateByType(TlsCertificateType::MAIL);
        // if no current or latest is newer than current
        if ($latest && (!$currentCert || $latest->getId() > $currentCert->getId())) {
            $latestCert = $latest;
        }

        return new JsonResponse([
            'current' => $currentCert ? new TlsCertificateObject($currentCert) : null,
            // if another one is being generated
            'latest' => $latestCert ? new TlsCertificateObject($latestCert) : null
        ]);
    }

    #[Route('/tls/mail-certs', methods: 'POST')]
    public function generateMailTlsCert(): JsonResponse
    {
        //
    }

}