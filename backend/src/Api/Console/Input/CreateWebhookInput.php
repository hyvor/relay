<?php

namespace App\Api\Console\Input;

use Symfony\Component\Validator\Constraints as Assert;

class CreateWebhookInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Url]
    public string $url;

    #[Assert\NotBlank]
    public string $description;
}