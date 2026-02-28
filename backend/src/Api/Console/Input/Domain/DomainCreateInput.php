<?php

namespace App\Api\Console\Input\Domain;

use App\Service\App\Validator\DkimPrivateKey;
use Symfony\Component\Validator\Constraints as Assert;

class DomainCreateInput
{

    #[Assert\NotBlank]
    public string $domain;

    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/',
        message: 'DKIM selector must be a valid DNS label (alphanumeric and hyphens, max 63 chars)'
    )]
    public ?string $dkim_selector = null;

    #[DkimPrivateKey]
    public ?string $dkim_private_key = null;

}
