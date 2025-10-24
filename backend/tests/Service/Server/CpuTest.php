<?php

namespace App\Tests\Service\Server;

use App\Service\Server\Cpu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cpu::class)]
class CpuTest extends TestCase
{

    public function test_get_cores_returns_integer(): void
    {
        $cores = Cpu::getCores();
        $this->assertGreaterThan(0, $cores);
    }

}