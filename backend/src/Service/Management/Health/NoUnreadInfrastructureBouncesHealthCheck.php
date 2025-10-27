<?php

namespace App\Service\Management\Health;

use App\Entity\InfrastructureBounce;
use Doctrine\ORM\EntityManagerInterface;

class NoUnreadInfrastructureBouncesHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function check(): bool
    {
        /** @var InfrastructureBounce[] $unreadBounces */
        $unreadBounces = $this->em->getRepository(InfrastructureBounce::class)
            ->createQueryBuilder('ib')
            ->where('ib.is_read = :isRead')
            ->setParameter('isRead', false)
            ->orderBy('ib.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        if (count($unreadBounces) === 0) {
            return true;
        }

        $bounceData = [];
        foreach ($unreadBounces as $bounce) {
            $bounceData[] = [
                'id' => $bounce->getId(),
                'smtp_code' => $bounce->getSmtpCode(),
                'smtp_enhanced_code' => $bounce->getSmtpEnhancedCode(),
                'smtp_message' => $bounce->getSmtpMessage(),
                'send_recipient_id' => $bounce->getSendRecipientId(),
                'created_at' => $bounce->getCreatedAt()->format('c'),
            ];
        }

        $this->setData([
            'unread_count' => count($unreadBounces),
            'unread_bounces' => $bounceData,
        ]);

        return false;
    }
}

