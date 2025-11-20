<?php

namespace App\Service\Tls;

use App\Entity\TlsCertificate;
use Doctrine\ORM\EntityManagerInterface;

class TlsCertificateService
{

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getCertificateById(int $id): ?TlsCertificate
    {
        return $this->em->getRepository(TlsCertificate::class)->find($id);
    }

}