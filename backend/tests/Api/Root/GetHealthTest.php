<?php

namespace App\Tests\Api\Root;

use App\Api\Root\RootApiController;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RootApiController::class)]
class GetHealthTest extends WebTestCase
{

    public function test_health(): void
    {
        $this->client->request('GET', '/api/health');
        $this->assertResponseIsSuccessful();
    }

}