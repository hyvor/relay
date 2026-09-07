<?php

namespace App\Service\App\Cache;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;

final class JsonMarshaller implements MarshallerInterface
{
    /**
     * @param array<string, mixed> $values
     * @param array<int, string>|null $failed
     * @param-out list<string> $failed
     * @return array<string, string>
     */
    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = [];
        $failed = [];

        foreach ($values as $key => $value) {
            try {
                $serialized[$key] = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $failed[] = $key;
            }
        }

        return $serialized;
    }

    public function unmarshall(string $value): mixed
    {
        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }
}
