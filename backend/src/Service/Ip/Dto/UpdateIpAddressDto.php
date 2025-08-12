<?php

namespace App\Service\Ip\Dto;

use App\Entity\Queue;
use App\Util\OptionalPropertyTrait;

class UpdateIpAddressDto
{

    use OptionalPropertyTrait;

    public ?Queue $queue;

}
