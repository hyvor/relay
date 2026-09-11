<?php

namespace App\Service\InfrastructureBounce;

use App\Entity\InfrastructureBounce;
use App\Entity\SendRecipient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

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
     * @return array<int, array{bounce: InfrastructureBounce, send_uuid: ?string, recipient_email: ?string}>
     */
    public function getInfrastructureBounces(int $limit, int $offset, ?bool $isRead = null): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('ib', 'sr.address AS recipientEmail', 's.uuid AS sendUuid')
            ->from(InfrastructureBounce::class, 'ib')
            ->leftJoin(SendRecipient::class, 'sr', Join::WITH, 'sr.id = ib.send_recipient_id')
            ->leftJoin('sr.send', 's')
            ->orderBy('ib.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($isRead !== null) {
            $qb->where('ib.is_read = :isRead')
                ->setParameter('isRead', $isRead);
        }

        /** @var array<int, array{0: InfrastructureBounce, sendUuid: ?string, recipientEmail: ?string}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(fn(array $row) => [
            'bounce' => $row[0],
            'send_uuid' => $row['sendUuid'],
            'recipient_email' => $row['recipientEmail'],
        ], $rows);
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

        /** @var int $result */
        $result = $qb->getQuery()->execute();
        return $result;
    }
}

