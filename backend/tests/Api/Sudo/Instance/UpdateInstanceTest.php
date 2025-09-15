<?php

namespace App\Tests\Api\Sudo\Instance;

use App\Api\Sudo\Controller\InstanceController;
use App\Api\Sudo\Input\UpdateInstanceInput;
use App\Entity\ServerTask;
use App\Entity\Type\ServerTaskType;
use App\Service\Instance\Dto\UpdateInstanceDto;
use App\Service\Instance\InstanceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InstanceController::class)]
#[CoversClass(UpdateInstanceInput::class)]
#[CoversClass(InstanceService::class)]
#[CoversClass(UpdateInstanceDto::class)]
class UpdateInstanceTest extends WebTestCase
{

    public function test_updates_instance_domain(): void
    {
        $instance = InstanceFactory::createOne();
        DomainFactory::createOne([
            'project' => $instance->getSystemProject()
        ]);

        $response = $this->sudoApi(
            'PATCH',
            '/instance',
            [
                'domain' => 'examples.com',
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame("examples.com", $instance->getDomain());

        $serverTask = $this->em->getRepository(ServerTask::class)->findAll();

        $this->assertCount(1, $serverTask);
        $this->assertSame(ServerTaskType::UPDATE_STATE, $serverTask[0]->getType());
    }

}
