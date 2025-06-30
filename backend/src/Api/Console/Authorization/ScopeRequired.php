<?php

namespace App\Api\Console\Authorization;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ScopeRequired
{
    public function __construct(public Scope $scope) {}
}