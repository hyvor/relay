<?php

namespace App\Api\Sudo\Input;

class UpdateServerInput
{

    public int $api_workers {
        set {
            $this->apiWorkersSet = true;
            $this->api_workers = $value;
        }
    }

    public int $email_workers {
        set {
            $this->emailWorkersSet = true;
            $this->email_workers = $value;
        }
    }

    public int $webhook_workers {
        set {
            $this->webhookWorkersSet = true;
            $this->webhook_workers = $value;
        }
    }

    public bool $apiWorkersSet = false;
    public bool $emailWorkersSet = false;
    public bool $webhookWorkersSet = false;

}