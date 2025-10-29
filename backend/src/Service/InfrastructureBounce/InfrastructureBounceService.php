<?php

namespace App\Service\InfrastructureBounce;

use App\Entity\InfrastructureBounce;
use Doctrine\ORM\EntityManagerInterface;

class InfrastructureBounceService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createInfrastructureBounce(
        int $sendRecipientId,
        int $smtpCode,
        string $smtpEnhancedCode,
        string $smtpMessage
    ): InfrastructureBounce {
        $infrastructureBounce = new InfrastructureBounce();
        $infrastructureBounce->setCreatedAt(new \DateTimeImmutable());
        $infrastructureBounce->setUpdatedAt(new \DateTimeImmutable());
        $infrastructureBounce->setIsRead(false);
        $infrastructureBounce->setSendRecipientId($sendRecipientId);
        $infrastructureBounce->setSmtpCode($smtpCode);
        $infrastructureBounce->setSmtpEnhancedCode($smtpEnhancedCode);
        $infrastructureBounce->setSmtpMessage($smtpMessage);

        $this->em->persist($infrastructureBounce);
        $this->em->flush();

        return $infrastructureBounce;
    }

    public function markAsRead(InfrastructureBounce $infrastructureBounce): void
    {
        $infrastructureBounce->setIsRead(true);
        $infrastructureBounce->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param bool|null $isRead
     * @return InfrastructureBounce[]
     */
    public function getInfrastructureBounces(int $limit, int $offset, ?bool $isRead = null): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('ib')
            ->from(InfrastructureBounce::class, 'ib')
            ->orderBy('ib.created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($isRead !== null) {
            $qb->where('ib.is_read = :isRead')
                ->setParameter('isRead', $isRead);
        }

        return $qb->getQuery()->getResult();
    }

    public function getInfrastructureBounceById(int $id): ?InfrastructureBounce
    {
        return $this->em->getRepository(InfrastructureBounce::class)->find($id);
    }

    public function markAllUnreadAsRead(): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->update(InfrastructureBounce::class, 'ib')
            ->set('ib.is_read', ':isRead')
            ->set('ib.updated_at', ':updatedAt')
            ->where('ib.is_read = :currentIsRead')
            ->setParameter('isRead', true)
            ->setParameter('updatedAt', new \DateTimeImmutable())
            ->setParameter('currentIsRead', false);

        return $qb->getQuery()->execute();
    }
}

