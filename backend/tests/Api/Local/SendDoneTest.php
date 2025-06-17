<?php

namespace Api\Local;

use App\Tests\Case\WebTestCase;

class SendDoneTest extends WebTestCase
{

    public function test_fails_on_no_send_found(): void
    {
        $this->localApi(
            'POST',
            '/send/done',
            [
                'sendId' => 9999,
                'status' => 'sent',
            ]
        );

        $this->assertResponseStatusCodeSame(422);

        $json = $this->getJson();
        $this->assertSame('Send not found', $json['message']);
    }

}