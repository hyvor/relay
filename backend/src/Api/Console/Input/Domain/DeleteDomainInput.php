<?php

namespace App\Api\Console\Input\Domain;

use Symfony\Component\Validator\Constraints as Assert;

class DeleteDomainInput
{

    #[Assert\When(
        'this.domain == null',
        constraints: [
            new Assert\NotBlank(message: 'Either id or domain must be provided.'),
        ]
    )]
    public ?int $id = null;

    #[Assert\When(
        'this.id == null',
        constraints: [
            new Assert\NotBlank(message: 'Either id or domain must be provided.'),
        ]
    )]
    public ?string $domain = null;

}