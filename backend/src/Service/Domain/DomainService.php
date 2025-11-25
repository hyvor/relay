<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Type\DomainStatus;
use App\Repository\DomainRepository;
use App\Service\Domain\Dto\UpdateDomainDto;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Exception\DomainDeletionFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DomainService
{

    use ClockAwareTrait;

    public function __construct(
        private DomainRepository $domainRepository,
        private EntityManagerInterface $em,
        private Encryption $encryption,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDomainById(int $domainId): ?Domain
    {
        return $this->domainRepository->find($domainId);
    }

    public function getDomainByProjectAndName(Project $project, string $domainName): ?Domain
    {
        return $this->domainRepository->findOneBy(['project' => $project, 'domain' => $domainName]);
    }

    public function createDomain(
        Project $project,
        string $domainName,
        ?string $dkimSelector = null,
        ?string $customDkimPublicKey = null,
        ?string $customDkimPrivateKey = null,
        bool $flush = true,
        bool $dispatch = true
    ): Domain {
        $domain = new Domain();
        $domain->setCreatedAt($this->now());
        $domain->setUpdatedAt($this->now());
        $domain->setProject($project);
        $domain->setDomain($domainName);
        $domain->setStatus(DomainStatus::PENDING);
        $domain->setStatusChangedAt($this->now());

        $dkimSelector = $dkimSelector ?? Dkim::generateDkimSelector();
        $domain->setDkimSelector($dkimSelector);

        if ($customDkimPublicKey) {
            // both must be provided
            assert(is_string($customDkimPrivateKey));

            $domain->setDkimPublicKey($customDkimPublicKey);
            $domain->setDkimPrivateKeyEncrypted($this->encryption->encryptString($customDkimPrivateKey));
        } else {
            [
                'public' => $publicKey,
                'private' => $privateKey,
            ] = Dkim::generateDkimKeys();

            $domain->setDkimPublicKey($publicKey);
            $domain->setDkimPrivateKeyEncrypted($this->encryption->encryptString($privateKey));
        }

        $this->em->persist($domain);

        if ($flush) {
            $this->em->flush();
        }

        if ($dispatch) {
            $this->eventDispatcher->dispatch(new DomainCreatedEvent($domain));
        }

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

    /**
     * @throws DomainDeletionFailedException
     */
    public function deleteDomain(Domain $domain): void
    {
        if ($domain->getStatus() === DomainStatus::SUSPENDED) {
            throw new DomainDeletionFailedException('Suspended domains can not be deleted.');
        }

        $domainClone = clone $domain;

        $this->em->remove($domain);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new DomainDeletedEvent($domainClone));
    }


    /**
     * @return array{total: int, active: int}
     */
    public function getDomainsCounts(): array
    {
        $query = $this->domainRepository->createQueryBuilder('d')
            ->select('COUNT(d.id) as total')
            ->addSelect('SUM(CASE WHEN d.status = :activeStatus THEN 1 ELSE 0 END) as active')
            ->setParameter('activeStatus', DomainStatus::ACTIVE)
            ->getQuery();

        /** @var array<string, int> $result */
        $result = $query->getSingleResult();
        return [
            'total' => $result['total'],
            'active' => $result['active'],
        ];
    }
}
