<?php

namespace App\Api\Console\Input;

use App\Entity\Type\WebhooksEventEnum;
use Symfony\Component\Validator\Constraints as Assert;

class CreateWebhookInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Url(requireTld: false)]
    public string $url;

    public string $description;

    /**
     * @var string[]
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Choice(callback: 'getWebhookEventValues'),
    ])]
    public array $events;

    /**
     * @return string[]
     */
    public static function getWebhookEventValues(): array
    {
        return array_column(WebhooksEventEnum::cases(), 'value');
    }
}