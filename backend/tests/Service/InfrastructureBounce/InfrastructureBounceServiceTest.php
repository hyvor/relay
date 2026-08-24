<?php

namespace App\Tests\Service\InfrastructureBounce;

use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use function Zenstruck\Foundry\Persistence\refresh;

#[CoversClass(InfrastructureBounceService::class)]
class InfrastructureBounceServiceTest extends KernelTestCase
{
    public function test_create_infrastructure_bounce(): void
    {
        $bounce = $this->getService(InfrastructureBounceService::class)->createInfrastructureBounce(
            42,
            550,
            '5.7.1',
            'Relay access denied'
        );

        $this->assertFalse($bounce->isRead());
        $this->assertSame(42, $bounce->getSendRecipientId());
        $this->assertSame(550, $bounce->getSmtpCode());
        $this->assertSame('5.7.1', $bounce->getSmtpEnhancedCode());
        $this->assertSame('Relay access denied', $bounce->getSmtpMessage());
        $this->assertGreaterThan(0, $bounce->getId());
    }

    public function test_mark_as_read(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => false,
            'updated_at' => new \DateTimeImmutable('-1 day'),
        ]);
        $oldUpdatedAt = $bounce->getUpdatedAt();

        $this->getService(InfrastructureBounceService::class)->markAsRead($bounce);

        $bounce = refresh($bounce);
        $this->assertTrue($bounce->isRead());
        $this->assertGreaterThan($oldUpdatedAt, $bounce->getUpdatedAt());
    }

    public function test_get_infrastructure_bounce_by_id(): void
    {
        $bounce = InfrastructureBounceFactory::createOne();

        $found = $this->getService(InfrastructureBounceService::class)
            ->getInfrastructureBounceById($bounce->getId());
        $this->assertNotNull($found);
        $this->assertSame($bounce->getId(), $found->getId());

        $this->assertNull(
            $this->getService(InfrastructureBounceService::class)->getInfrastructureBounceById(999999)
        );
    }

    public function test_get_infrastructure_bounces_ordered_and_filtered(): void
    {
        $first = InfrastructureBounceFactory::createOne([
            'is_read' => false,
        ]);
        $second = InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        $service = $this->getService(InfrastructureBounceService::class);

        $all = $service->getInfrastructureBounces(10, 0);
        $firstOfAll = $all->first();
        $this->assertNotFalse($firstOfAll);
        $this->assertSame($second->getId(), $firstOfAll->getId());
        $this->assertCount(2, $all);

        $unread = $service->getInfrastructureBounces(10, 0, false);
        $firstUnread = $unread->first();
        $this->assertNotFalse($firstUnread);
        $this->assertCount(1, $unread);
        $this->assertSame($first->getId(), $firstUnread->getId());

        $read = $service->getInfrastructureBounces(10, 0, true);
        $firstRead = $read->first();
        $this->assertNotFalse($firstRead);
        $this->assertCount(1, $read);
        $this->assertSame($second->getId(), $firstRead->getId());
    }

    public function test_mark_all_unread_as_read(): void
    {
        InfrastructureBounceFactory::createMany(3, [
            'is_read' => false,
        ]);
        InfrastructureBounceFactory::createMany(2, [
            'is_read' => true,
        ]);

        $service = $this->getService(InfrastructureBounceService::class);

        $count = $service->markAllUnreadAsRead();
        $this->assertSame(3, $count);

        $unread = $service->getInfrastructureBounces(10, 0, false);
        $this->assertCount(0, $unread);

        $read = $service->getInfrastructureBounces(10, 0, true);
        $this->assertCount(5, $read);
    }

    public function test_get_send_uuids_by_recipient_ids(): void
    {
        $recipient = SendRecipientFactory::createOne();
        $service = $this->getService(InfrastructureBounceService::class);

        $map = $service->getSendUuidsByRecipientIds([
            $recipient->getId(),
            999999,
        ]);

        $this->assertSame($recipient->getSend()->getUuid(), $map[$recipient->getId()]);
        $this->assertArrayNotHasKey(999999, $map);

        $this->assertSame([], $service->getSendUuidsByRecipientIds([]));
    }
}
