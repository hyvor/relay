<?php

namespace App\Service\Send\Dto;

class SendingAttachment
{

    public string $content;
    public ?string $contentType = null;
    public ?string $name = null;

}