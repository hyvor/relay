<?php

namespace App\Service\Domain\MessageHandler;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\DomainStatusService;
use App\Service\Domain\Exception\DkimVerificationFailedException;
use App\Service\Domain\Message\ReverifyVerifiedAndWarningDomainsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Hyvor\Internal\Bundle\Log\ContextualLogger;

/**
 * Reverifies all verified and warning domains to make sure they are still valid.
 *
 * If not valid,
 * verified -> warning
 * warning -> pending
 */
#[AsMessageHandler]
class ReverifyVerifiedAndWarningDomainsMessageHandler
{

    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        private EntityManagerInterface $em,
        private DomainStatusService $domainStatusService,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($logger, self::class);
    }

    public function __invoke(ReverifyVerifiedAndWarningDomainsMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Reverifying domains');

        $iterator = $this->em->createQuery(
            <<<DQL
            SELECT d
            FROM App\Entity\Domain d
            WHERE d.status IN (:statuses)
            ORDER BY d.id ASC
        DQL
        )
            ->setParameter('statuses', [DomainStatus::ACTIVE, DomainStatus::WARNING])
            ->toIterable();

        $i = 0;
        $batchSize = $message->getBatchSize();
        $errors = 0; // number of unexpected errors on dns verification
        // maximum number of errors before stopping the process
        // generally means the DoH server is down or unreachable
        $maxErrors = 10;

        foreach ($iterator as $domain) {
            assert($domain instanceof Domain);

            try {
                $this->domainStatusService->updateAfterDkimVerification($domain);
                $this->em->persist($domain);
            } catch (DkimVerificationFailedException $exception) {
                $this->logger->error(
                    'Error reverifying domain',
                    [
                        'domain_id' => $domain->getId(),
                        'domain' => $domain->getDomain(),
                        'error' => $exception->getMessage()
                    ]
                );

                $errors++;
                if ($errors >= $maxErrors) {
                    $this->logger->error(
                        'Too many errors while reverifying domains, stopping the process',
                        [
                            'errors' => $errors,
                            'max_errors' => $maxErrors
                        ]
                    );
                    break;
                } else {
                    continue;
                }
            }

            $i++;
            if (($i % $batchSize) === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();

        $duration = microtime(true) - $startTime;
        $this->logger->info(
            'Reverification completed',
            [
                'handler' => self::class,
                'duration_seconds' => $duration,
                'success_count' => $i,
                'error_count' => $errors,
            ]
        );
    }
}