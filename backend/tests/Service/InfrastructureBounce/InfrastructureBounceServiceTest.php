<?php

namespace App\Tests\Service\InfrastructureBounce;

use App\Entity\InfrastructureBounce;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InfrastructureBounceService::class)]
class InfrastructureBounceServiceTest extends KernelTestCase
{
    public function test_create_infrastructure_bounce_persists_entity_with_defaults(): void
    {
        /** @var InfrastructureBounceService $service */
        $service = $this->container->get(InfrastructureBounceService::class);

        $bounce = $service->createInfrastructureBounce(
            sendRecipientId: 42,
            smtpCode: 550,
            smtpEnhancedCode: '5.1.1',
            smtpMessage: 'mailbox unavailable',
        );

        $this->assertGreaterThan(0, $bounce->getId());
        $this->assertSame(42, $bounce->getSendRecipientId());
        $this->assertSame(550, $bounce->getSmtpCode());
        $this->assertSame('5.1.1', $bounce->getSmtpEnhancedCode());
        $this->assertSame('mailbox unavailable', $bounce->getSmtpMessage());
        $this->assertFalse($bounce->isRead());

        $this->em->clear();
        $reloaded = $this->em->getRepository(InfrastructureBounce::class)->find($bounce->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(42, $reloaded->getSendRecipientId());
        $this->assertSame(550, $reloaded->getSmtpCode());
        $this->assertSame('5.1.1', $reloaded->getSmtpEnhancedCode());
        $this->assertSame('mailbox unavailable', $reloaded->getSmtpMessage());
        $this->assertFalse($reloaded->isRead());
    }
}
