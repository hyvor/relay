<?php

namespace App\Tests\Service\User\Comms;

use App\Entity\ProjectUser;
use App\Entity\User;
use App\Service\User\Comms\MemberRemovedListener;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\NewsletterFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use App\Tests\Factory\UserFactory;
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
