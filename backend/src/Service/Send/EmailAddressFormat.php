<?php

namespace App\Service\Send;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Mime\Address;

/**
 * @phpstan-type ArrayAddress array{email: string, name?: string}
 * @phpstan-type StringOrArrayAddress string|ArrayAddress
 */
#[Exclude]
class EmailAddressFormat
{

    /**
     * @param string $email must be a valid email address
     */
    public static function getDomainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        assert(count($parts) > 1, 'Email address must contain a domain part: ' . $email);
        return $parts[1];
    }

    /**
     * Creates an Address object from a string or an associative array.
     * @param StringOrArrayAddress $inputAddress
     */
    public static function createAddressFromInput(string|array $inputAddress): Address
    {
        if (is_string($inputAddress)) {
            return new Address($inputAddress);
        } else {
            return new Address($inputAddress['email'], $inputAddress['name'] ?? '');
        }
    }

    /**
     * @param StringOrArrayAddress|StringOrArrayAddress[] $inputAddresses
     * @return Address[]
     */
    public static function createAddressesFromInput(string|array $inputAddresses): array
    {
        if (is_string($inputAddresses)) {
            return [self::createAddressFromInput($inputAddresses)];
        }

        if (array_key_exists('email', $inputAddresses)) {
            /** @var ArrayAddress $inputAddresses */
            return [self::createAddressFromInput($inputAddresses)];
        }

        $addresses = [];

        foreach ($inputAddresses as $inputAddress) {
            $addresses[] = self::createAddressFromInput($inputAddress);
        }

        return $addresses;
    }

}