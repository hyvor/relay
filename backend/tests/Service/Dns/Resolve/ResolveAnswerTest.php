<?php

namespace App\Tests\Service\Dns\Resolve;

use App\Service\Dns\Resolve\ResolveAnswer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolveAnswer::class)]
class ResolveAnswerTest extends TestCase
{

    public function test_cleans_txt(): void
    {

        $simpleAnswer = new ResolveAnswer(
            name: 'example.com',
            data: 'answer',
            type: 16, // TXT
            ttl: 3600,
        );
        $this->assertSame('answer', $simpleAnswer->getCleanedTxt());

        $quoted = new ResolveAnswer(
            name: 'example.com',
            data: '"answer"',
            type: 16, // TXT
            ttl: 3600,
        );
        $this->assertSame('answer', $quoted->getCleanedTxt());

        $multiPart = new ResolveAnswer(
            name: 'example.com',
            data: '"part1" "part2"',
            type: 16, // TXT
            ttl: 3600,
        );
        $this->assertSame('part1part2', $multiPart->getCleanedTxt());

    }

}