<?php

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

    private(set) bool $queueSet = false;

}
