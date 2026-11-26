<?php

namespace App\Api\Console\Controller\Org;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Send;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Billing\BillingInterface;
use Hyvor\Internal\Billing\License\RelayLicense;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\OrgEndpoint;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class BillingUsageController extends AbstractController
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private BillingInterface $billing,
        private InternalConfig $internalConfig,
    ) {
    }

    #[Route('/billing/usage', methods: 'GET')]
    #[OrgEndpoint]
    public function usage(ConsoleAuthResults $consoleAuth): JsonResponse
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            throw new NotFoundHttpException('Billing is only available on cloud deployments.');
        }

        $organizationId = $consoleAuth->getOrganizationId();
        $license = $this->billing->license($organizationId)->license;
        $relayLicense = $license instanceof RelayLicense ? $license : RelayLicense::trial();

        $monthStart = $this->now()->modify('first day of this month')->setTime(0, 0);

        $emails = (int)$this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Send::class, 's')
            ->join('s.project', 'p')
            ->where('p.organization_id = :org')
            ->andWhere('s.created_at >= :start')
            ->setParameter('org', $organizationId)
            ->setParameter('start', $monthStart)
            ->getQuery()
            ->getSingleScalarResult();

        $projects = (int)$this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Project::class, 'p')
            ->where('p.organization_id = :org')
            ->setParameter('org', $organizationId)
            ->getQuery()
            ->getSingleScalarResult();

        $domains = (int)$this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Domain::class, 'd')
            ->join('d.project', 'p')
            ->where('p.organization_id = :org')
            ->setParameter('org', $organizationId)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'emails' => ['used' => $emails, 'limit' => $relayLicense->emails],
            'projects' => ['used' => $projects, 'limit' => $relayLicense->projects],
            'domains' => ['used' => $domains, 'limit' => $relayLicense->domains],
        ]);
    }
}
