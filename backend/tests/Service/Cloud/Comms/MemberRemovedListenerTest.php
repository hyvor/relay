<?php

namespace App\Tests\Service\Cloud\Comms;

use App\Entity\ProjectUser;
use App\Service\Cloud\Comms\MemberRemovedListener;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\Member\MemberRemoved;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MemberRemovedListener::class)]
class MemberRemovedListenerTest extends WebTestCase
{
    public function test_delete_users(): void
    {
        $removingMemberUserId = 12345;
        $removingMemberOrganizationId = 1;

        ProjectUserFactory::createMany(2, [
            'project' => ProjectFactory::new([
                'organization_id' => $removingMemberOrganizationId
            ]),
            'user_id' => $removingMemberUserId
		]);

        ProjectUserFactory::createMany(3, [
            'project' => ProjectFactory::new([
                'organization_id' => 2
            ]),
            'user_id' => $removingMemberUserId
		]);

        ProjectUserFactory::createMany(4, [
            'project' => ProjectFactory::new([
                'organization_id' => $removingMemberOrganizationId
            ]),
		]);

        $this->getEd()->dispatch(new MemberRemoved($removingMemberOrganizationId, $removingMemberUserId));

        $remainingUsers = $this->getEm()->getRepository(ProjectUser::class)->findAll();
        $this->assertCount(7, $remainingUsers);
    }
}
