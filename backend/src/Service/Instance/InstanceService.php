<?php

namespace App\Service\Instance;

use App\Entity\Instance;
use App\Repository\InstanceRepository;
use App\Service\Domain\Dkim;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Clock\ClockAwareTrait;

class InstanceService
{
    use ClockAwareTrait;

    public const string DEFAULT_DOMAIN = 'relay.hyvor.localhost';
    public const string DEFAULT_DKIM_SELECTOR = 'default';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InstanceRepository $instanceRepository,
        private readonly Encryption $encryption
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
        }

        return $instance;
    }

    public function createInstance(): Instance
    {
        [
            'public' => $publicKey,
            'private' => $privateKey,
        ] = Dkim::generateDkimKeys();

        $instance = new Instance();
        $instance
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setDomain(self::DEFAULT_DOMAIN)
            ->setDkimPublicKey($publicKey)
            ->setDkimPrivateKeyEncrypted($this->encryption->encryptString($privateKey));

        $this->em->persist($instance);
        $this->em->flush();

        return $instance;
    }
}
