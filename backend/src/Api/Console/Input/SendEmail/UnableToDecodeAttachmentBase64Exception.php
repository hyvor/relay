<?php

declare(strict_types=1);

namespace App\Api\Console\Input\SendEmail;

class UnableToDecodeAttachmentBase64Exception extends \Exception
{
    public function __construct(public int $attachmentIndex)
    {
        parent::__construct();
    }

}
