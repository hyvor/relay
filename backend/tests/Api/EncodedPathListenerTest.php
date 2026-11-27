<?php

namespace App\Tests\Api;

use App\Api\EncodedPathListener;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EncodedPathListener::class)]
class EncodedPathListenerTest extends WebTestCase
{

    public function test_rejects_percent_encoded_sudo_path(): void
    {
        $this->client->request('GET', '/api/%73udo/servers');
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_rejects_percent_encoded_local_path(): void
    {
        $this->client->request('GET', '/api/%6cocal/state');
        $this->assertResponseStatusCodeSame(404);
    }

}
