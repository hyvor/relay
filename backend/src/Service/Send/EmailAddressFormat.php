<?php

namespace App\Service\Send;

use Symfony\Component\Mime\Address;

class EmailAddressFormat
{

    /**
     * @param string $email must be a valid email address
     * @throws \Exception
     */
    public static function getDomainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        assert(count($parts) > 1, 'Email address must contain a domain part: ' . $email);
        return $parts[1];
    }

    /**
     * Creates an Address object from a string or an associative array.
     * @param string|array{email: string, name?: string} $inputAddress
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
     * @deprecated
     * @param string|array{email: string, name?: string}|array<string|array{email: string, name?: string}> $inputAddresses
     * @return Address[]
     */
    /*public static function createAddressesFromInput(string|array $inputAddresses, bool $nestedAllowed = true): array
    {
        if (is_string($inputAddresses)) {
            return [self::createAddressFromInput($inputAddresses)];
        }

        if (array_key_exists('email', $inputAddresses)) {
            /** @var array{email: string, name?: string} $to /
            $to = $inputAddresses;
            return [self::createAddressFromInput($to)];
        }

        if (!$nestedAllowed) {
            throw new \LogicException('Nested addresses are not supported in this context.');
        }

        $addresses = [];

        foreach ($inputAddresses as $inputAddress) {
            $addresses[] = self::createAddressesFromInput($inputAddress, false)[0];
        }

        return $addresses;
    }*/

}