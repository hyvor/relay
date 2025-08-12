<?php

namespace App\Service\Instance;

use App\Entity\Instance;
use App\Entity\Type\ProjectSendType;
use App\Repository\InstanceRepository;
use App\Service\Domain\Dkim;
use App\Service\Instance\Dto\UpdateInstanceDto;
use App\Service\Project\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class InstanceService
{
    use ClockAwareTrait;

    public const string DEFAULT_DOMAIN = 'relay.hyvor.localhost';
    public const string DEFAULT_DKIM_SELECTOR = 'default';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InstanceRepository $instanceRepository,
        private readonly Encryption $encryption,
        private ProjectService $projectService,
        private LoggerInterface $logger,
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
            // this should generally not happen in production
            // useful for tests also
            $instance = $this->createInstance();

            $this->logger->critical('Instance not found, created a new one. This should not happen in production.');
        }

        return $instance;
    }

    public function createInstance(): Instance
    {
        [
            'public' => $publicKey,
            'private' => $privateKey,
        ] = Dkim::generateDkimKeys();

        $systemProject = $this->projectService->createProject(0, 'System', ProjectSendType::TRANSACTIONAL);

        $instance = new Instance();
        $instance
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setDomain(self::DEFAULT_DOMAIN)
            ->setDkimPublicKey($publicKey)
            ->setDkimPrivateKeyEncrypted($this->encryption->encryptString($privateKey))
            ->setSystemProject($systemProject);

        $this->em->persist($instance);
        $this->em->persist($systemProject);
        $this->em->flush();

        return $instance;
    }

    public function updateInstance(Instance $instance, UpdateInstanceDto $updates): void
    {
        if ($updates->domainSet) {
            $instance->setDomain($updates->domain);
        }

        if ($updates->privateNetworkCidrSet) {
            $instance->setPrivateNetworkCidr($updates->privateNetworkCidr);
        }

        $instance->setUpdatedAt($this->now());

        $this->em->persist($instance);
        $this->em->flush();
    }
}
