<?php

namespace App\Api\Console\Input;

use App\Entity\Type\WebhooksEventEnum;

use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;
class UpdateWebhookInput
{
    use OptionalPropertyTrait;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Url]
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