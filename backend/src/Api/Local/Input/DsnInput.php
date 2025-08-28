<?php

namespace App\Api\Local\Input;

use Symfony\Component\Validator\Constraints as Assert;

class DsnInput
{
    #[Assert\NotBlank]
    public string $ReadableText;

    /** @var DsnRecipientsInput[]  */
    #[Assert\NotBlank]
    public array $Recipients;

}
