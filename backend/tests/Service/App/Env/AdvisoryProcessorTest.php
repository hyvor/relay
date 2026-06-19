<?php

namespace App\Tests\Service\App\Env;

use App\Service\App\Env\AdvisoryProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdvisoryProcessor::class)]
class AdvisoryProcessorTest extends TestCase
{

    public function test_replace(): void
    {
        $processor = new AdvisoryProcessor();

        $this->assertSame(
            'postgresql+advisory://user:pass@localhost:5432/dbname',
            $processor->getEnv('advisory', 'TEST_ENV', fn() => 'postgresql://user:pass@localhost:5432/dbname')
        );

        $this->assertSame(
            '123',
            $processor->getEnv('advisory', 'TEST_ENV', fn() => 123)
        );

        $this->assertSame(
            'string',
            $processor->getProvidedTypes()['advisory']
        );
    }
}
