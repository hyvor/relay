<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum QueueType: string
{
    case DEFAULT = 'default';
    case DEDICATED = 'dedicated';
    case CUSTOM = 'custom'; // NOT YET IMPLEMENTED, but will allow custom queues

}
