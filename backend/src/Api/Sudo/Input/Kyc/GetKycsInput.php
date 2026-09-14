<?php

namespace App\Api\Sudo\Input\Kyc;

use App\Service\Kyc\KycService;
use Symfony\Component\Validator\Constraints as Assert;

class GetKycsInput
{

    #[Assert\Choice(choices: ['pending', 'approved', 'rejected'])]
    public ?string $status = null;

    #[Assert\Choice(choices: KycService::SORTABLE_COLUMNS)]
    public string $sort_by = 'submitted_at';

    #[Assert\Choice(choices: KycService::SORT_DIRECTIONS)]
    public string $sort = 'desc';

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 30;

    #[Assert\PositiveOrZero]
    public int $offset = 0;

}
