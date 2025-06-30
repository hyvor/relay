<?php

namespace App\Service\Email\Dto;

class SendingAttachment
{

    public string $content;
    public ?string $contentType = null;
    public ?string $name = null;

}