<?php

namespace App\Tests\Service\Cloud\Comms;

use App\Entity\ProjectUser;
use App\Service\Cloud\Comms\UserDeletedListener;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\User\UserDeleted;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UserDeletedListener::class)]
class UserDeletedListenerTest extends WebTestCase
{
    public function test_delete_users(): void
    {
        $deletingUserId = 12345;

        ProjectUserFactory::createMany(2, [
            'project' => ProjectFactory::new(),
            'user_id' => $deletingUserId
		]);

        ProjectUserFactory::createMany(3, [
            'project' => ProjectFactory::new(),
            'user_id' => $deletingUserId
		]);

        ProjectUserFactory::createMany(4, [
            'project' => ProjectFactory::createOne(),
		]);

        $this->getEd()->dispatch(new UserDeleted($deletingUserId));

        $remainingUsers = $this->getEm()->getRepository(ProjectUser::class)->findAll();
        $this->assertCount(4, $remainingUsers);
    }
}
