<?php

namespace App\Api\Console\Input\Kyc;

use App\Entity\Type\KycBusinessType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class KycSubmitInput
{

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $full_name;

    public KycBusinessType $business_type;

    #[Assert\Length(max: 255)]
    public ?string $business_name = null;

    #[Assert\Callback]
    public function validateBusinessName(ExecutionContextInterface $context): void
    {
        if ($this->business_type === KycBusinessType::COMPANY && trim((string)$this->business_name) === '') {
            $context
                ->buildViolation('Business name is required for companies.')
                ->atPath('business_name')
                ->addViolation();
        }
    }

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Za-z]{2}$/', message: 'Country must be a valid 2-letter country code.')]
    public string $country;

    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public string $address;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^\+?[0-9 ()\-]+$/', message: 'Please enter a valid phone number.')]
    public string $phone;

    #[Assert\Length(max: 255)]
    #[Assert\Url(message: 'Please enter a valid URL.')]
    public ?string $website = null;

}
