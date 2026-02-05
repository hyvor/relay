<?php

namespace App\Service\Cloud\Comms;

use App\Repository\ProjectUserRepository;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\Member\MemberRemoved;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MemberRemovedListener
{
    public function __construct(
        private ProjectUserRepository $puRepo,
        private ProjectUserService $puService,
    )
    {
    }

    public function __invoke(MemberRemoved $event): void
	{
		$proj_users = $this->puRepo->findByUserAndOrganization(
			$event->getUserId(),
			$event->getOrganizationId()
		);

		foreach($proj_users as $proj_user) {
			$this->puService->deleteProjectUser($proj_user);
		}
    }
}
