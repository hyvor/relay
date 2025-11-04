<?php

namespace App\Service\Instance;

use App\Entity\Instance;
use App\Entity\Type\ProjectSendType;
use App\Repository\InstanceRepository;
use App\Service\Domain\Dkim;
use App\Service\Domain\DomainService;
use App\Service\Instance\Dto\UpdateInstanceDto;
use App\Service\Instance\Event\InstanceUpdatedEvent;
use App\Service\Project\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

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
        private DomainService $domainService,
        private EventDispatcherInterface $eventDispatcher,
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
            // @codeCoverageIgnoreStart

            // this should generally not happen in production
            // useful for tests also
            $instance = $this->createInstance();
            $this->logger->critical('Instance not found, created a new one. This should not happen in production.');
            // @codeCoverageIgnoreEnd
        }

        return $instance;
    }

    public function createInstance(): Instance
    {
        [
            'public' => $publicKey,
            'private' => $privateKey,
        ] = Dkim::generateDkimKeys();

        $newProject = $this->projectService->createProject(
            0,
            'System',
            ProjectSendType::TRANSACTIONAL,
            flush: false
        );
        $systemProject = $newProject['project'];
        $systemProjectDomain = $this->domainService->createDomain(
            $systemProject,
            self::DEFAULT_DOMAIN,
            dkimSelector: self::DEFAULT_DKIM_SELECTOR,
            customDkimPublicKey: $publicKey,
            customDkimPrivateKey: $privateKey,
            flush: false,
            dispatch: false
        );

        $instance = new Instance();
        $instance
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setUuid(Uuid::v4())
            ->setDomain(self::DEFAULT_DOMAIN)
            ->setDkimPublicKey($publicKey)
            ->setDkimPrivateKeyEncrypted($this->encryption->encryptString($privateKey))
            ->setSystemProject($systemProject);

        $this->em->persist($instance);
        $this->em->persist($systemProject);
        $this->em->persist($systemProjectDomain);
        $this->em->flush();

        return $instance;
    }

    public function updateInstance(Instance $instance, UpdateInstanceDto $updates): void
    {
        $oldInstance = clone $instance;

        if ($updates->domainSet) {
            $instance->setDomain($updates->domain);
        }

        $instance->setUpdatedAt($this->now());

        $this->em->persist($instance);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new InstanceUpdatedEvent($oldInstance, $instance, $updates));
    }
}
