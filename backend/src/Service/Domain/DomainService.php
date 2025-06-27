<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Entity\Project;
use App\Repository\DomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;

class DomainService
{

    public function __construct(
        private DomainRepository $domainRepository,
        private EntityManagerInterface $em,
        private Encryption $encryption
    )
    {
    }

    public function getDomainById(int $domainId): ?Domain
    {
        return $this->domainRepository->find($domainId);
    }

    public function getDomainByProjectAndName(Project $project, string $domainName): ?Domain
    {
        return $this->domainRepository->findOneBy(['project' => $project, 'domain' => $domainName]);
    }

    public function createDomain(Project $project, string $domainName): Domain
    {
        $domain = new Domain();
        $domain->setProject($project);
        $domain->setDomain($domainName);
        $domain->setCreatedAt(new \DateTimeImmutable());
        $domain->setUpdatedAt(new \DateTimeImmutable());

        $domain->setDkimSelector(Dkim::generateDkimSelector());

        [
            'public' => $publicKey,
            'private' => $privateKey,
        ] = Dkim::generateDkimKeys();

        $domain->setDkimPublicKey($publicKey);
        $domain->setDkimPrivateKeyEncrypted($this->encryption->encryptString($privateKey));

        $this->em->persist($domain);
        $this->em->flush();

        return $domain;
    }

}