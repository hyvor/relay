<?php

namespace App\Api\Console\Object;

use App\Service\Send\Dto\SendContent;

class SendContentObject
{
    public ?string $body_html;
    public ?string $body_text;
    /** @var array<string, string> */
    public array $headers;
    public string $raw;

    public function __construct(SendContent $content)
    {
        $this->body_html = $content->bodyHtml;
        $this->body_text = $content->bodyText;
        $this->headers = $content->headers;
        $this->raw = $content->raw;
    }
}
