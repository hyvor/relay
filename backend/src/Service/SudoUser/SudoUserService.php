<?php

namespace App\Service\SudoUser;

use App\Entity\SudoUser;
use App\Repository\SudoUserRepository;
use Doctrine\ORM\EntityManagerInterface;

class SudoUserService
{
    public function __construct(
        private SudoUserRepository $sudoUserRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function findByHyvorUserId(int $hyvorUserId): ?SudoUser
    {
        return $this->em->getRepository(SudoUser::class)->findOneBy(['hyvor_user_id' => $hyvorUserId]);
    }

    public function isSudoUser(int $hyvorUserId): bool
    {
        return $this->findByHyvorUserId($hyvorUserId) !== null;
    }

    public function createSudoUser(int $hyvorUserId): SudoUser
    {
        $sudoUser = new SudoUser();
        $sudoUser->setHyvorUserId($hyvorUserId);
        
        $this->em->persist($sudoUser);
        $this->em->flush();
        
        return $sudoUser;
    }

    public function removeSudoUser(int $hyvorUserId): bool
    {
        $sudoUser = $this->findByHyvorUserId($hyvorUserId);
        
        if ($sudoUser === null) {
            return false;
        }
        
        $this->em->remove($sudoUser);
        $this->em->flush();
        
        return true;
    }

    /**
     * @return SudoUser[]
     */
    public function getAllSudoUsers(): array
    {
        return $this->sudoUserRepository->findAll();
    }
} 
