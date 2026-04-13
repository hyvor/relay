<?php

namespace App\Service\Sudo;

use Hyvor\Internal\Sudo\SudoRoleInterface;

enum SudoRole: string implements SudoRoleInterface
{
    case SUDO = 'sudo';

    public function getPermissions(): array
    {
        return match ($this) {
            self::SUDO => SudoPermission::cases()
        };
    }
}
