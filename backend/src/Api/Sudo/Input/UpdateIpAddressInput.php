<?php

namespace App\Api\Sudo\Input;

use App\Util\OptionalPropertyTrait;

class UpdateIpAddressInput
{
	use OptionalPropertyTrait;

	public ?int $queue_id;
}
