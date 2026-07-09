<?php

declare(strict_types=1);

namespace App\Api\Console\Authorization;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OrganizationOptional
{
}
