<?php

namespace App\Api\Local\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ArfInput
{
    #[Assert\NotBlank]
    public string $ReadableText;
    #[Assert\NotBlank]
    public string $FeedbackType;
    #[Assert\NotBlank]
    public string $UserAgent;
    #[Assert\NotBlank]
    public string $OriginalMailFrom;
    #[Assert\NotBlank]
    public string $OriginalRcptTo;
    #[Assert\NotBlank]
    public string $MessageId;
}
