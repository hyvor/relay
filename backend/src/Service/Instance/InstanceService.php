<?php

namespace App\Service\Instance;

use App\Entity\Instance;
use App\Entity\Type\ProjectSendType;
use App\Repository\InstanceRepository;
use App\Service\App\Config;
use App\Service\Domain\Dkim;
use App\Service\Domain\DomainService;
use App\Service\Project\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Uid\Uuid;

class InstanceService
{
    use ClockAwareTrait;

    public const string DEFAULT_DKIM_SELECTOR = 'default';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InstanceRepository $instanceRepository,
        private readonly Encryption $encryption,
        private ProjectService $projectService,
        private LoggerInterface $logger,
        private DomainService $domainService,
        private Config $config,
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
            $this->config->getInstanceDomain(),
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
            ->setDomain($this->config->getInstanceDomain())
            ->setDkimPublicKey($publicKey)
            ->setDkimPrivateKeyEncrypted($this->encryption->encryptString($privateKey))
            ->setSystemProject($systemProject);

        $this->em->persist($instance);
        $this->em->persist($systemProject);
        $this->em->persist($systemProjectDomain);
        $this->em->flush();

        return $instance;
    }
}
