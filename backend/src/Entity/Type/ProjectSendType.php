<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum ProjectSendType: string
{
    case TRANSACTIONAL = 'transactional';
    case DISTRIBUTIONAL = 'distributional';

}
