<?php

namespace App\Api\Local\Input;

use App\Entity\Type\BounceReason;
use Symfony\Component\Validator\Constraints as Assert;

class DsnInput
{
    #[Assert\NotBlank]
    public string $ReadableText;

    /**
     * X-Relay-Recipient header of the original message (SendRecipient uuid).
     * Used when the provider redacts the recipient address.
     * Not all DSNs include headers though.
     */
    public string $RecipientUuid = '';

    /**
     * Can be empty or redacted by some (most?) providers
     */
    public string $EmailAddress = '';

    #[Assert\NotBlank]
    public string $Status;

    #[Assert\NotBlank]
    public string $Action;

    public ?BounceReason $BounceReason = null;
}
