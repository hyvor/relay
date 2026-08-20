<?php

namespace App\Tests\Api\Console;

use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
class OpenApiSpecTest extends WebTestCase
{
    public function test_console_openapi_spec_is_generated(): void
    {
        $this->client->request('GET', '/api/doc/console.json');

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);

        $spec = json_decode($content, true);
        $this->assertIsArray($spec);
        $this->assertArrayHasKey('paths', $spec);

        $info = $spec['info'] ?? null;
        $this->assertIsArray($info);
        $this->assertSame('Hyvor Relay', $info['title'] ?? null);

        $paths = $spec['paths'];
        $this->assertIsArray($paths);

        $pathNames = array_keys($paths);
        $this->assertContains('/api/console/init', $pathNames);
        $this->assertContains('/api/console/project', $pathNames);
        $this->assertContains('/api/console/api-keys', $pathNames);
        $this->assertContains('/api/console/domains', $pathNames);
        $this->assertContains('/api/console/project-users', $pathNames);
        $this->assertContains('/api/console/sends', $pathNames);
        $this->assertContains('/api/console/suppressions', $pathNames);
        $this->assertContains('/api/console/webhooks', $pathNames);
        $this->assertContains('/api/console/analytics/stats', $pathNames);
        $this->assertContains('/api/console/analytics/sends/chart', $pathNames);
    }
}
