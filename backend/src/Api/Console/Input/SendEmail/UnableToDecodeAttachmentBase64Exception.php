<?php

namespace App\Api\Console\Input\SendEmail;

class UnableToDecodeAttachmentBase64Exception extends \Exception
{

    public function __construct(public int $attachmentIndex)
    {
        parent::__construct();
    }

}