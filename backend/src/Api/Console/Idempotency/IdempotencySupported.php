<?php

declare(strict_types=1);

namespace App\Api\Console\Idempotency;

#[\Attribute(\Attribute::TARGET_METHOD)]
class IdempotencySupported
{
}
