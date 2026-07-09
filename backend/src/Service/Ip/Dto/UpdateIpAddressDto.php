<?php

declare(strict_types=1);

namespace App\Service\Ip\Dto;

use App\Entity\Queue;

class UpdateIpAddressDto
{
    public ?Queue $queue {
        set {
            $this->queueSet = true;
            $this->queue = $value;
        }
    }

    public private(set) bool $queueSet = false;

}
