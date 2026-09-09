<?php

namespace App\Tests\Service\App\Cache;

use App\Service\App\Cache\JsonMarshaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonMarshaller::class)]
class JsonMarshallerTest extends TestCase
{
    public function test_marshalls_values_as_json(): void
    {
        $marshaller = new JsonMarshaller();
        $failed = null;

        $values = $marshaller->marshall(['key' => ['host' => 'mx.example.com']], $failed);

        self::assertSame(['key' => '{"host":"mx.example.com"}'], $values);
        self::assertSame([], $failed);
        self::assertSame(['host' => 'mx.example.com'], $marshaller->unmarshall($values['key']));
    }

    public function test_reports_values_that_cannot_be_encoded(): void
    {
        $marshaller = new JsonMarshaller();
        $failed = null;
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        $values = $marshaller->marshall(['good' => true, 'bad' => $resource], $failed);
        fclose($resource);

        self::assertSame(['good' => 'true'], $values);
        self::assertSame(['bad'], $failed);
    }

    public function test_rejects_values_larger_than_shared_cache_limit(): void
    {
        $marshaller = new JsonMarshaller();
        $failed = null;

        $values = $marshaller->marshall(['large' => str_repeat('x', 1024 * 1024)], $failed);

        self::assertSame([], $values);
        self::assertSame(['large'], $failed);
    }

    public function test_reports_exceptions_from_json_serializable_values(): void
    {
        $marshaller = new JsonMarshaller();
        $failed = null;
        $value = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                throw new \RuntimeException('Cannot serialize');
            }
        };

        $values = $marshaller->marshall(['bad' => $value, 'good' => true], $failed);

        self::assertSame(['good' => 'true'], $values);
        self::assertSame(['bad'], $failed);
    }
}
