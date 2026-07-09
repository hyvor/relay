<?php

declare(strict_types=1);

namespace App\Util;

trait OptionalPropertyTrait
{
    /**
     * Checks if the property is INITIALIZED
     */
    public function hasProperty(string $property): bool
    {
        try {
            $_ = $this->{$property};
            return true;
        } catch (\Error $e) {
            return false;
        }
    }

}
