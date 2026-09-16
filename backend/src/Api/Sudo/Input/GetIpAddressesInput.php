<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;

class GetIpAddressesInput
{

    #[Assert\Ip()]
    public ?string $ip_address = null;

}
