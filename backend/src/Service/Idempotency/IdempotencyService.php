<?php

namespace App\Service\Idempotency;

use App\Entity\ApiIdempotencyRecord;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class IdempotencyService
{

    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    public function getIdempotencyRecordByProjectEndpointAndKey(
        Project $project,
        string $endpoint,
        string $key
    ): ?ApiIdempotencyRecord
    {
        return $this->em->getRepository(ApiIdempotencyRecord::class)
            ->findOneBy([
                'project' => $project,
                'endpoint' => $endpoint,
                'idempotency_key' => $key
            ]);
    }

    public function createIdempotencyRecord(
        Project $project,
        string $endpoint,
        string $key,
        JsonResponse $jsonResponse
    ): ApiIdempotencyRecord
    {

        $jsonDecode = new JsonDecode();
        /** @var array<mixed> $data */
        $data = $jsonDecode->decode(
            (string) $jsonResponse->getContent(),
            JsonEncoder::FORMAT,
            ['json_decode_associative' => true]
        );

        $record = new ApiIdempotencyRecord();
        $record->setProject($project);
        $record->setEndpoint($endpoint);
        $record->setIdempotencyKey($key);
        $record->setResponse($data);
        $record->setStatusCode($jsonResponse->getStatusCode());

        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

}