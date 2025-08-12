<?php

namespace App\Service\Blacklist\Exception;

class DnsblLookupException extends \Exception
{

    public function __construct(
        string $blacklist,
        string $query,
        string $ip,
        string $error
    ) {
        $message = "DNSBL lookup failed for {$blacklist} with query {$query} (IP: $ip): {$error}";
        parent::__construct($message);
    }

}