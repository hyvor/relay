<?php

namespace App\Api\Sudo\Input;

use App\Entity\Type\WarmupStatus;
use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UpdateWarmupScheduleInput
{
	use OptionalPropertyTrait;

	public WarmupStatus $status;

	/** @var array<int>|null */
	#[Assert\Sequentially([
		new Assert\Type('array'),
		new Assert\Count(min: 30, max: 30),
		new Assert\All(new Assert\Type('integer')),
		new Assert\Callback([self::class, 'validateSchedule'])
	])]
	public ?array $schedule;

	/**
	 * @param array<int>|null $schedule
	 */
	public static function validateSchedule(?array $schedule, ExecutionContextInterface $context): void {
		if ($schedule === null) {
			return;
		}

		for ($i = 1; $i < 30; $i++) {
			if ($schedule[$i] < $schedule[$i - 1]) {
				$context
					->buildViolation('Schedule values must not decrease.')
					->atPath('schedule')
					->addViolation();

				break;
			}
		}
	}
}
