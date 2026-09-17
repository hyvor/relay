<?php

namespace App\Api\Sudo\Input\Kyc;

use App\Entity\Type\KycStatus;
use App\Service\Kyc\KycService;
use Symfony\Component\Validator\Constraints as Assert;

class GetKycsInput
{

    #[Assert\Choice(callback: [KycStatus::class, 'getValues'])]
    public ?string $status = null;

    #[Assert\Positive]
    public ?int $organization_id = null;

    #[Assert\Choice(choices: KycService::SORTABLE_COLUMNS)]
    public string $sort_by = 'created_at';

    #[Assert\Choice(choices: KycService::SORT_DIRECTIONS)]
    public string $sort = 'desc';

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 30;

    #[Assert\PositiveOrZero]
    public int $offset = 0;

}
