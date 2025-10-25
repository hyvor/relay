<?php

namespace App\Service\IncomingMail\Dto;

class ComplaintDto implements \JsonSerializable
{
    public function __construct(
        private string $text,
        private string $feedback_type
    ) {
    }

    /**
     * @return array{ text: string, feedback_type: string }
     */
    public function jsonSerialize(): array
    {
        return ['text' => $this->text, 'feedback_type' => $this->feedback_type];
    }
}
