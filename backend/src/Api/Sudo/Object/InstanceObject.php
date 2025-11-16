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

    public function __construct(Instance $instance, string $instanceDomain)
    {
        $this->domain = $instanceDomain;
        $this->dkim_host = Dkim::dkimHost(InstanceService::DEFAULT_DKIM_SELECTOR, $instanceDomain);
        $this->dkim_txt_value = Dkim::dkimTxtValue($instance->getDkimPublicKey());
    }

}
