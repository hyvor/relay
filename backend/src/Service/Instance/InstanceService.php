<?php

namespace App\Service\Instance;

use App\Entity\Instance;
use App\Repository\InstanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class InstanceService
{
    use ClockAwareTrait;

    public const string DEFAULT_DOMAIN = 'relay.hyvor.localhost';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InstanceRepository $instanceRepository,
    ) {
    }

    public function tryGetInstance(): ?Instance
    {
        return $this->instanceRepository->findFirst();
    }

    public function getInstance(): Instance
    {
        $instance = $this->tryGetInstance();

        if ($instance === null) {
            // this should generally not happen
            $instance = $this->createInstance();
        }

        return $instance;
    }

    public function createInstance(): Instance
    {
        $instance = new Instance();
        $instance
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setDomain(self::DEFAULT_DOMAIN);

        $this->em->persist($instance);
        $this->em->flush();

        return $instance;
    }
}
