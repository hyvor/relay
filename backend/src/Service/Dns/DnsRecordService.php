<?php

namespace App\Service\Dns;

use App\Entity\DnsRecord;
use App\Repository\DnsRecordRepository;
use App\Service\Dns\Dto\CreateDnsRecordDto;
use App\Service\Dns\Dto\UpdateDnsRecordDto;
use App\Service\Dns\Event\CustomDnsRecordsChangedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DnsRecordService
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DnsRecordRepository $dnsRecordRepository,
        private EventDispatcherInterface $ed,
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

        $this->ed->dispatch(new CustomDnsRecordsChangedEvent());

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

        $this->ed->dispatch(new CustomDnsRecordsChangedEvent());
    }

    public function deleteDnsRecord(DnsRecord $dnsRecord): void
    {
        $this->em->remove($dnsRecord);
        $this->em->flush();

        $this->ed->dispatch(new CustomDnsRecordsChangedEvent());
    }
}
