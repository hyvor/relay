<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum ServerTaskType: string
{
    case UPDATE_STATE = 'update_state';
}
