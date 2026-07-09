<?php

declare(strict_types=1);

namespace App\Api\Console\Authorization;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ScopeRequired
{
    public function __construct(public Scope $scope)
    {
    }
}
