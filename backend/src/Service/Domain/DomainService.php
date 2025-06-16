<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Repository\DomainRepository;

class DomainService
{

    public function __construct(
        private DomainRepository $domainRepository,
    )
    {
    }

    public function getDomainByName(string $domainName): ?Domain
    {
        return $this->domainRepository->findOneBy(['domain' => $domainName]);
    }

}