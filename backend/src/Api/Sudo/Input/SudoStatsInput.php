<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SudoStatsInput
{
    public const int MAX_CUSTOM_DAYS = 366;

    #[Assert\Choice(choices: ['1y', '30d', '7d', 'today', 'custom'])]
    public string $period = 'today';

    /** Required when period=custom (Y-m-d) */
    #[Assert\Date]
    public ?string $from = null;

    /** Required when period=custom (Y-m-d) */
    #[Assert\Date]
    public ?string $to = null;

    #[Assert\Callback]
    public function validateCustomRange(ExecutionContextInterface $context): void
    {
        if ($this->period !== 'custom') {
            return;
        }

        if ($this->from === null || $this->to === null) {
            $context->buildViolation('from and to are required for a custom period')->atPath('from')->addViolation();
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to)) {
            return; // handled by Assert\Date
        }

        $from = new \DateTimeImmutable($this->from);
        $to = new \DateTimeImmutable($this->to);

        if ($from > $to) {
            $context->buildViolation('from must be before or equal to to')->atPath('from')->addViolation();
        } elseif ((int) $from->diff($to)->days + 1 > self::MAX_CUSTOM_DAYS) {
            $context->buildViolation('The range cannot exceed ' . self::MAX_CUSTOM_DAYS . ' days')
                ->atPath('to')
                ->addViolation();
        }
    }
}
