<?php

namespace App\Tests\Service\Idempotency;

use App\Entity\ApiIdempotencyRecord;
use App\Service\Idempotency\Message\ClearExpiredIdempotencyRecordsMessage;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ApiIdempotencyRecordFactory;

class ClearExpiredIdempotencyRecordsMessageHandlerTest extends KernelTestCase
{

    public function test_deletes_older_records(): void
    {

        $r1 = ApiIdempotencyRecordFactory::createOne([
            'created_at' => new \DateTimeImmutable('-25 hours'),
        ]);

        $r2 = ApiIdempotencyRecordFactory::createOne([
            'created_at' => new \DateTimeImmutable('-23 hours'),
        ]);

        $transport = $this->transport('scheduler_global');
        $transport->send(new ClearExpiredIdempotencyRecordsMessage());
        $transport->throwExceptions()->process();

        $repository = $this->em->getRepository(ApiIdempotencyRecord::class);
        $this->assertNull($repository->find($r1->getId()));
        $this->assertNotNull($repository->find($r2->getId()));

    }

}