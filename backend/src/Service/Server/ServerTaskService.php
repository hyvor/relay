<?php

namespace App\Service\Server;

use App\Entity\Server;
use App\Entity\ServerTask;
use App\Entity\Type\ServerTaskType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Illuminate\Support\Arr;

class ServerTaskService
{
    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    /*
     * @param array<string, string> $payload
     */
    public function createTask(Server $server, ServerTaskType $serverTaskType, array $payload): ServerTask
    {
        $task = new ServerTask();
        $task->setServer($server)
            ->setType($serverTaskType)
            ->setPayload($payload)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    /**
     * @return ServerTask[]
     */
    public function getTaskForServer(Server $server): array
    {
        return $this->em->getRepository(ServerTask::class)->findBy(['server' => $server]);
    }

    public function deleteTask(ServerTask $task): void
    {
        $this->em->remove($task);
        $this->em->flush();
    }
}
