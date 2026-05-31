<?php

namespace App\Service\ApiKey;

use Symfony\Component\HttpFoundation\IpUtils;

class AllowedIp
{
    public const int IPV4_MIN_PREFIX = 24;
    public const int IPV6_MIN_PREFIX = 48;

    /**
     * Validates a single allow-list entry. Returns null on success, an error
     * message describing the failure otherwise.
     */
    public static function validateEntry(string $entry): ?string
    {
        if ($entry === '') {
            return 'Allowed IP entry must not be empty.';
        }

        $slash = strpos($entry, '/');
        if ($slash === false) {
            $ip = $entry;
            $prefix = null;
        } else {
            $ip = substr($entry, 0, $slash);
            $prefixPart = substr($entry, $slash + 1);
            if ($prefixPart === '' || !ctype_digit($prefixPart)) {
                return "Invalid CIDR prefix in '$entry'.";
            }
            $prefix = (int) $prefixPart;
        }

        $isV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isV6 = !$isV4 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (!$isV4 && !$isV6) {
            return "'$entry' is not a valid IPv4 or IPv6 address.";
        }

        if ($isV4) {
            $effectivePrefix = $prefix ?? 32;
            if ($effectivePrefix < self::IPV4_MIN_PREFIX || $effectivePrefix > 32) {
                return "IPv4 CIDR prefix must be between /" . self::IPV4_MIN_PREFIX . " and /32 (got '$entry').";
            }
        } else {
            $effectivePrefix = $prefix ?? 128;
            if ($effectivePrefix < self::IPV6_MIN_PREFIX || $effectivePrefix > 128) {
                return "IPv6 CIDR prefix must be between /" . self::IPV6_MIN_PREFIX . " and /128 (got '$entry').";
            }
        }

        return null;
    }

    /**
     * Returns the canonical form of a valid entry. Caller MUST have validated
     * via validateEntry() first.
     */
    public static function normalizeEntry(string $entry): string
    {
        $slash = strpos($entry, '/');
        $ip = $slash === false ? $entry : substr($entry, 0, $slash);
        $suffix = $slash === false ? '' : substr($entry, $slash);

        $packed = inet_pton($ip);
        if ($packed === false) {
            return $entry;
        }
        $normalizedIp = inet_ntop($packed);

        return $normalizedIp . $suffix;
    }

    /**
     * @param string[] $entries
     */
    public static function matches(string $clientIp, array $entries): bool
    {
        if ($entries === []) {
            return false;
        }
        return IpUtils::checkIp($clientIp, $entries);
    }
}
