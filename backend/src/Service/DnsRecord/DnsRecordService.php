<?php

namespace App\Service\DnsRecord;

use App\Entity\DnsRecord;
use App\Repository\DnsRecordRepository;
use App\Service\DnsRecord\Dto\CreateDnsRecordDto;
use App\Service\DnsRecord\Dto\UpdateDnsRecordDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class DnsRecordService
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DnsRecordRepository $dnsRecordRepository,
    ) {
    }

    /**
     * @return DnsRecord[]
     */
    public function getAllDnsRecords(): array
    {
        return $this->dnsRecordRepository->findBy([], ['subdomain' => 'ASC', 'type' => 'ASC']);
    }

    public function getDnsRecordById(int $id): ?DnsRecord
    {
        return $this->dnsRecordRepository->find($id);
    }

    public function createDnsRecord(CreateDnsRecordDto $dto): DnsRecord
    {
        $dnsRecord = new DnsRecord();
        $dnsRecord
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setType($dto->type)
            ->setSubdomain($dto->subdomain)
            ->setContent($dto->content)
            ->setTtl($dto->ttl)
            ->setPriority($dto->priority);

        $this->em->persist($dnsRecord);
        $this->em->flush();

        return $dnsRecord;
    }

    public function updateDnsRecord(DnsRecord $dnsRecord, UpdateDnsRecordDto $updates): void
    {
        if ($updates->typeSet) {
            $dnsRecord->setType($updates->type);
        }

        if ($updates->subdomainSet) {
            $dnsRecord->setSubdomain($updates->subdomain);
        }

        if ($updates->contentSet) {
            $dnsRecord->setContent($updates->content);
        }

        if ($updates->ttlSet) {
            $dnsRecord->setTtl($updates->ttl);
        }

        if ($updates->prioritySet) {
            $dnsRecord->setPriority($updates->priority);
        }

        $dnsRecord->setUpdatedAt($this->now());

        $this->em->persist($dnsRecord);
        $this->em->flush();
    }

    public function deleteDnsRecord(DnsRecord $dnsRecord): void
    {
        $this->em->remove($dnsRecord);
        $this->em->flush();
    }
}
