<?php

namespace App\Api\Sudo\Object;

use App\Entity\Instance;
use App\Service\Domain\Dkim;
use App\Service\Instance\InstanceService;

class InstanceObject
{

    public string $domain;

    public string $dkim_host;
    public string $dkim_txt_value;
    public string $private_network_cidr;

    public function __construct(Instance $instance)
    {
        $this->domain = $instance->getDomain();
        $this->dkim_host = Dkim::dkimHost(InstanceService::DEFAULT_DKIM_SELECTOR, $instance->getDomain());
        $this->dkim_txt_value = Dkim::dkimTxtValue($instance->getDkimPublicKey());
        $this->private_network_cidr = $instance->getPrivateNetworkCidr();
    }

}