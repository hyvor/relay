<?php

namespace App\Tests\Service\Project;

use App\Entity\Send;
use App\Service\Project\Event\ProjectsDeletedEvent;
use App\Service\Project\ProjectsDeletedMailListener;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Auth\AuthUser;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectsDeletedMailListener::class)]
class ProjectsDeletedMailListenerTest extends KernelTestCase
{

    public function test_sends_one_notification_per_owner(): void
    {
        $instance = InstanceFactory::new()->withDefaultDkim()->create();
        DomainFactory::createOne([
            'project' => $instance->getSystemProject(),
            'domain' => 'system.example.com',
        ]);
        QueueFactory::createTransactional();

        $ownerOne = AuthFake::generateUser(['id' => 101, 'email' => 'one@example.com', 'name' => 'Owner One']);
        $ownerTwo = AuthFake::generateUser(['id' => 202, 'email' => 'two@example.com', 'name' => 'Owner Two']);
        AuthFake::enableForSymfony(
            $this->container,
            usersDatabase: [$ownerOne, $ownerTwo],
        );

        $ownerOneProjectA = ProjectFactory::createOne(['user_id' => 101, 'name' => 'Alpha']);
        $ownerOneProjectB = ProjectFactory::createOne(['user_id' => 101, 'name' => 'Beta']);
        $ownerTwoProject = ProjectFactory::createOne(['user_id' => 202, 'name' => 'Gamma']);

        $this->getEd()->dispatch(new ProjectsDeletedEvent([
            $ownerOneProjectA->_real(),
            $ownerOneProjectB->_real(),
            $ownerTwoProject->_real(),
        ]));

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(2, $sends);

        $sendsByTo = [];
        foreach ($sends as $send) {
            $recipient = $send->getRecipients()->first();
            $this->assertNotFalse($recipient);
            $sendsByTo[$recipient->getAddress()] = $send;
        }

        $this->assertArrayHasKey('one@example.com', $sendsByTo);
        $this->assertArrayHasKey('two@example.com', $sendsByTo);

        $ownerOneSend = $sendsByTo['one@example.com'];
        $this->assertSame('Your projects have been deleted', $ownerOneSend->getSubject());
        $this->assertSame('noreply@system.example.com', $ownerOneSend->getFromAddress());
        $html = $ownerOneSend->getBodyHtml();
        $this->assertNotNull($html);
        $this->assertStringContainsString('Owner One', $html);
        $this->assertStringContainsString('Alpha', $html);
        $this->assertStringContainsString('Beta', $html);
        $this->assertStringContainsString('30 days', $html);

        $ownerTwoSend = $sendsByTo['two@example.com'];
        $html = $ownerTwoSend->getBodyHtml();
        $this->assertNotNull($html);
        $this->assertStringContainsString('Gamma', $html);
    }

    public function test_returns_early_when_no_projects(): void
    {
        InstanceFactory::createOne();

        $this->getEd()->dispatch(new ProjectsDeletedEvent([]));

        $this->assertCount(0, $this->em->getRepository(Send::class)->findAll());
    }

    public function test_logs_error_when_system_domain_missing(): void
    {
        InstanceFactory::createOne();
        QueueFactory::createTransactional();

        $project = ProjectFactory::createOne(['user_id' => 101]);

        $this->getEd()->dispatch(new ProjectsDeletedEvent([$project->_real()]));

        $this->assertCount(0, $this->em->getRepository(Send::class)->findAll());
        $this->assertTrue(
            $this->getTestLogger()->hasErrorThatContains(
                'Cannot send project deletion notification: system domain or transactional queue is missing.'
            )
        );
    }

    public function test_logs_error_when_transactional_queue_missing(): void
    {
        $instance = InstanceFactory::new()->withDefaultDkim()->create();
        DomainFactory::createOne([
            'project' => $instance->getSystemProject(),
            'domain' => 'system.example.com',
        ]);

        $project = ProjectFactory::createOne(['user_id' => 101]);

        $this->getEd()->dispatch(new ProjectsDeletedEvent([$project->_real()]));

        $this->assertCount(0, $this->em->getRepository(Send::class)->findAll());
        $this->assertTrue(
            $this->getTestLogger()->hasErrorThatContains(
                'Cannot send project deletion notification: system domain or transactional queue is missing.'
            )
        );
    }

    public function test_skips_owner_when_auth_user_not_found(): void
    {
        $instance = InstanceFactory::new()->withDefaultDkim()->create();
        DomainFactory::createOne([
            'project' => $instance->getSystemProject(),
            'domain' => 'system.example.com',
        ]);
        QueueFactory::createTransactional();

        $knownOwner = AuthFake::generateUser(['id' => 101, 'email' => 'known@example.com']);
        AuthFake::enableForSymfony(
            $this->container,
            usersDatabase: [$knownOwner],
        );

        $knownProject = ProjectFactory::createOne(['user_id' => 101, 'name' => 'Known']);
        $missingProject = ProjectFactory::createOne(['user_id' => 999, 'name' => 'Missing']);

        $this->getEd()->dispatch(new ProjectsDeletedEvent([
            $knownProject->_real(),
            $missingProject->_real(),
        ]));

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $sends);
        $recipient = $sends[0]->getRecipients()->first();
        $this->assertNotFalse($recipient);
        $this->assertSame('known@example.com', $recipient->getAddress());

        $this->assertTrue(
            $this->getTestLogger()->hasWarningThatContains(
                'Skipping project deletion notification: user not found.'
            )
        );
    }
}
