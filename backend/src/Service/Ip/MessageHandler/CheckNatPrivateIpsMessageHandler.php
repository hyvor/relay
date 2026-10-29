<?php

namespace App\Service\Ip\MessageHandler;

use App\Entity\IpAddress;
use App\Service\Ip\Message\CheckNatPrivateIpsMessage;
use App\Service\Ip\ServerIpResolver\PublicIpResolver;
use App\Service\Server\ServerService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Log\ContextualLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckNatPrivateIpsMessageHandler
{

    use ClockAwareTrait;

    private LoggerInterface $logger;

    public function __construct(
        private EntityManagerInterface $em,
        private ServerService $serverService,
        private PublicIpResolver $publicIpResolver,
        LoggerInterface $logger,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($logger, self::class);
    }

    public function __invoke(CheckNatPrivateIpsMessage $message): void
    {
        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->logger->warning(
                'NAT check received, but no server found for the current hostname.'
            );
            return;
        }

        /** @var IpAddress[] $ips */
        $ips = $this->em->getRepository(IpAddress::class)->findBy(['server' => $server], ['id' => 'ASC']);

        $invalidNats = [];
        $privateIpsExist = false;

        foreach ($ips as $ip) {
            $privateIp = $ip->getPrivateIpAddress();
            if ($privateIp === null) {
                continue;
            }

            $privateIpsExist = true;

            try {
                $resolvedPublicIp = $this->publicIpResolver->resolve($privateIp);

                if ($resolvedPublicIp !== $ip->getIpAddress()) {
                    $invalidNats[] = [
                        'private_ip' => $privateIp,
                        'expected_public_ip' => $ip->getIpAddress(),
                        'resolved_public_ip' => $resolvedPublicIp,
                        'error' => null,
                    ];
                }
            } catch (\Throwable $e) {
                $invalidNats[] = [
                    'private_ip' => $privateIp,
                    'expected_public_ip' => $ip->getIpAddress(),
                    'resolved_public_ip' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $server->setNatCheck(
            $privateIpsExist ? [
                'checked_at' => $this->now()->format(\DateTimeInterface::ATOM),
                'invalid_nats' => $invalidNats,
            ] : null
        );

        $this->em->persist($server);
        $this->em->flush();
    }

}
