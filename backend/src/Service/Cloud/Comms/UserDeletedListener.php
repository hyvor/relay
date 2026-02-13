<?php

namespace App\Service\Cloud\Comms;

use App\Entity\ProjectUser;
use App\Service\ProjectUser\ProjectUserService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\User\UserDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class UserDeletedListener
{
	public function __construct(
        private EntityManagerInterface $em,
        private ProjectUserService $puService,
    )
    {
    }

    public function __invoke(UserDeleted $event): void
	{
		$proj_users = $this->em->getRepository(ProjectUser::class)->findBy([
            'user_id' => $event->getUserId()
        ]);

		foreach($proj_users as $proj_user) {
			$this->puService->deleteProjectUser($proj_user, flush: false);
		}

        $this->em->flush();
    }
}
