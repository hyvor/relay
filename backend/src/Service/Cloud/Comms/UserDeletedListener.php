<?php

namespace App\Service\Cloud\Comms;

use App\Repository\ProjectUserRepository;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\User\UserDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class UserDeletedListener
{
	public function __construct(
        private ProjectUserRepository $puRepo,
        private ProjectUserService $puService,
    )
    {
    }

    public function __invoke(UserDeleted $event): void
	{
		$proj_users = $this->puRepo->findBy([
			'user_id' => $event->getUserId()
		]);

		foreach($proj_users as $proj_user) {
			$this->puService->deleteProjectUser($proj_user);
		}
    }
}
