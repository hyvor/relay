<?php

namespace App\Service\App\Cache;

use Doctrine\ORM\EntityManagerInterface;

class CacheProvider
{

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function create(): void
    {

        $conn = $this->em->getConnection();

    }

}