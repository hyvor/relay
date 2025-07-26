<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\CreateDnsRecordInput;
use App\Api\Sudo\Input\UpdateDnsRecordInput;
use App\Api\Sudo\Object\DnsRecordObject;
use App\Service\DnsRecord\Dto\CreateDnsRecordDto;
use App\Service\DnsRecord\Dto\UpdateDnsRecordDto;
use App\Service\DnsRecord\DnsRecordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DnsRecordController extends AbstractController
{
    public function __construct(
        private DnsRecordService $dnsRecordService,
    ) {
    }

    #[Route('/dns-records', methods: ['GET'])]
    public function getDnsRecords(): JsonResponse
    {
        $dnsRecords = $this->dnsRecordService->getAllDnsRecords();

        return new JsonResponse(
            array_map(fn($record) => new DnsRecordObject($record), $dnsRecords)
        );
    }

    #[Route('/dns-records', methods: ['POST'])]
    public function createDnsRecord(
        #[MapRequestPayload] CreateDnsRecordInput $input
    ): JsonResponse {
        $dto = new CreateDnsRecordDto(
            type: $input->type,
            subdomain: $input->subdomain,
            content: $input->content,
            ttl: $input->ttl,
            priority: $input->priority
        );

        $dnsRecord = $this->dnsRecordService->createDnsRecord($dto);

        return new JsonResponse(
            new DnsRecordObject($dnsRecord),
            201
        );
    }

    #[Route('/dns-records/{id}', methods: ['PATCH'])]
    public function updateDnsRecord(
        int $id,
        #[MapRequestPayload] UpdateDnsRecordInput $input
    ): JsonResponse {
        $dnsRecord = $this->dnsRecordService->getDnsRecordById($id);

        if ($dnsRecord === null) {
            throw new NotFoundHttpException('DNS record not found');
        }

        $updates = new UpdateDnsRecordDto();
        if ($input->typeSet) {
            $updates->type = $input->type;
        }
        if ($input->subdomainSet) {
            $updates->subdomain = $input->subdomain;
        }
        if ($input->contentSet) {
            $updates->content = $input->content;
        }
        if ($input->ttlSet) {
            $updates->ttl = $input->ttl;
        }
        if ($input->prioritySet) {
            $updates->priority = $input->priority;
        }

        $this->dnsRecordService->updateDnsRecord($dnsRecord, $updates);

        return new JsonResponse(
            new DnsRecordObject($dnsRecord)
        );
    }

    #[Route('/dns-records/{id}', methods: ['DELETE'])]
    public function deleteDnsRecord(int $id): JsonResponse
    {
        $dnsRecord = $this->dnsRecordService->getDnsRecordById($id);

        if ($dnsRecord === null) {
            throw new NotFoundHttpException('DNS record not found');
        }

        $this->dnsRecordService->deleteDnsRecord($dnsRecord);

        return new JsonResponse(null, 204);
    }
}
