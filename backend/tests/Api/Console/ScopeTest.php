<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Authorization\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scope::class)]
class ScopeTest extends TestCase
{

    public function test_methods(): void
    {
        $all = Scope::all();
        $this->assertContains('project.read', $all);

        $allExcept = Scope::allExcept([Scope::PROJECT_READ, Scope::DOMAINS_WRITE]);
        $this->assertNotContains('project.read', $allExcept);
        $this->assertNotContains('domains.write', $allExcept);
        $this->assertContains('sends.read', $allExcept);
    }

}