<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Entity\Project;
use App\Repository\DomainRepository;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Event\DomainVerifiedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DomainService
{

    use ClockAwareTrait;

    public function __construct(
        private DomainRepository $domainRepository,
        private EntityManagerInterface $em,
        private Encryption $encryption,
        private DkimVerificationService $dkimVerificationService,
        private EventDispatcherInterface $eventDispatcher
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

        $this->eventDispatcher->dispatch(new DomainCreatedEvent($domain));

        return $domain;
    }

    /**
     * @return ArrayCollection<int, Domain>
     */
    public function getProjectDomains(
        Project $project,
        ?string $search,
        int $limit,
        int $offset
    ): ArrayCollection {
        $qb = $this->domainRepository->createQueryBuilder('d');

        $qb
            ->distinct()
            ->where('d.project = :project')
            ->orderBy('d.id', 'DESC')
            ->setParameter('project', $project)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($search !== null) {
            $qb->andWhere('d.domain LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        /** @var Domain[] $results */
        $results = $qb->getQuery()->getResult();
        return new ArrayCollection($results);
    }

    public function verifyDkimAndUpdate(Domain $domain): void
    {
        $result = $this->dkimVerificationService->verify($domain);

        $domain->setDkimVerified($result->verified);
        $domain->setDkimCheckedAt($this->now());
        $domain->setDkimErrorMessage($result->errorMessage);

        $this->em->persist($domain);
        $this->em->flush();

        if ($result->verified) {
            $this->eventDispatcher->dispatch(new DomainVerifiedEvent($domain, $result));
        }
    }

    public function deleteDomain(Domain $domain): void
    {
        $this->em->remove($domain);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new DomainDeletedEvent($domain));
    }
}
