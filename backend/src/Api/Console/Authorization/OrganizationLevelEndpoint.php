<?php

declare(strict_types=1);

namespace App\Api\Console\Authorization;

// use for user-level endpoints in the Console API (/console/init) that
// is not project-specific
#[\Attribute(\Attribute::TARGET_METHOD)]
class OrganizationLevelEndpoint
{
}
