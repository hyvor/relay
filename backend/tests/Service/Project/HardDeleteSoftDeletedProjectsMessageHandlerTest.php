<?php

namespace App\Tests\Service\Project;

use App\Entity\ApiKey;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\ProjectUser;
use App\Service\Project\Message\HardDeleteSoftDeletedProjectsMessage;
use App\Service\Project\MessageHandler\HardDeleteSoftDeletedProjectsMessageHandler;
use App\Service\Project\ProjectService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HardDeleteSoftDeletedProjectsMessageHandler::class)]
#[CoversClass(ProjectService::class)]
class HardDeleteSoftDeletedProjectsMessageHandlerTest extends KernelTestCase
{
    public function test_hard_deletes_projects_soft_deleted_more_than_30_days_ago(): void
    {
        $oldId = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-31 days'),
        ])->getId();
        $recentId = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-29 days'),
        ])->getId();
        $activeId = ProjectFactory::createOne()->getId();

        $transport = $this->transport('scheduler_default');
        $transport->send(new HardDeleteSoftDeletedProjectsMessage());
        $transport->throwExceptions()->process();

        $this->em->clear();
        $repo = $this->em->getRepository(Project::class);
        $this->assertNull($repo->find($oldId));
        $this->assertNotNull($repo->find($recentId));
        $this->assertNotNull($repo->find($activeId));
    }

    public function test_hard_delete_cascades_related_rows(): void
    {
        $project = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-31 days'),
        ]);
        $projectId = $project->getId();
        $domainId = DomainFactory::createOne(['project' => $project])->getId();
        $apiKeyId = ApiKeyFactory::createOne(['project' => $project])->getId();
        $projectUserId = ProjectUserFactory::createOne(['project' => $project])->getId();

        $transport = $this->transport('scheduler_default');
        $transport->send(new HardDeleteSoftDeletedProjectsMessage());
        $transport->throwExceptions()->process();

        $this->em->clear();
        $this->assertNull($this->em->getRepository(Project::class)->find($projectId));
        $this->assertNull($this->em->getRepository(Domain::class)->find($domainId));
        $this->assertNull($this->em->getRepository(ApiKey::class)->find($apiKeyId));
        $this->assertNull($this->em->getRepository(ProjectUser::class)->find($projectUserId));
    }

    public function test_no_op_when_no_eligible_projects(): void
    {
        ProjectFactory::createMany(3);

        $transport = $this->transport('scheduler_default');
        $transport->send(new HardDeleteSoftDeletedProjectsMessage());
        $transport->throwExceptions()->process();

        $this->assertCount(3, $this->em->getRepository(Project::class)->findAll());
    }
}
