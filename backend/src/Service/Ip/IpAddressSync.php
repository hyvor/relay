<?php

namespace App\Service\Ip;

use App\Entity\Server;

/**
 * Syncs IP addresses of the server (network interfaces) with the database.
 * Usually called during instance initialization or manually via sudo
 */
class IpAddressSync
{

    public function sync(
        Server $server,
        bool $forceRecheckPublicIps = false,
    ): void {
        //
    }

}
