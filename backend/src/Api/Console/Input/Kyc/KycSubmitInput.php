<?php

namespace App\Api\Console\Input\Kyc;

use App\Entity\Type\KycAccountType;
use App\Entity\Type\KycContentOwnership;
use App\Entity\Type\KycSendingType;
use App\Service\Kyc\Countries;
use Symfony\Component\Validator\Constraints as Assert;

class KycSubmitInput
{
    public KycAccountType $account_type;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: Countries::NAMES, message: 'Please select a valid country.')]
    public string $country;

    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public string $address;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^(https?:\/\/.+|([a-z0-9-]+\.)+[a-z]{2,}(\/.*)?)$/i',
        message: 'Please enter a valid website.'
    )]
    public string $website;

    public KycContentOwnership $content_ownership;

    /**
     * @var list<string>
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, minMessage: 'Select at least one sending type.')]
    #[Assert\All([
        new Assert\Choice(enum: KycSendingType::class),
    ])]
    public array $sending_type;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    public string $use_case;
}
