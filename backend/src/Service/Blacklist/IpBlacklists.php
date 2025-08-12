<?php

namespace App\Service\Blacklist;

class IpBlacklists
{

    /**
     * @return IpBlacklist[]
     */
    public static function getBlacklists(): array
    {
        return [
            new IpBlacklist(
                'Barracuda',
                'b.barracudacentral.org',
                'https://www.barracudacentral.org/rbl/removal-request'
            ),
            new IpBlacklist(
                'Spamcop',
                'bl.spamcop.net',
                'https://www.spamcop.net/bl.shtml?ip='
            ),
            new IpBlacklist(
                'Mailspike',
                'bl.mailspike.net',
                'https://mailspike.io/ip_verify'
            ),
            new IpBlacklist(
                'PSBL',
                'psbl.surriel.com',
                'https://psbl.org/remove'
            ),
            new IpBlacklist(
                '0Spam',
                'bl.0spam.org',
                'https://0spam.org/'
            ),
        ];
    }

}