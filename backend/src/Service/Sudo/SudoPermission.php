<?php

declare(strict_types=1);

namespace App\Service\Sudo;

use Hyvor\Internal\Sudo\SudoPermissionInterface;

enum SudoPermission: string implements SudoPermissionInterface
{
    case ACCESS_SUDO = 'access_sudo';
}
