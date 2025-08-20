<?php

namespace App\Tests\Api\Local;

use App\Tests\Case\WebTestCase;

class IncomingBounceTest extends WebTestCase
{
    public function test_incoming_bounce(): void
    {
        $this->localApi(
            'POST',
            '/incoming/bounce',
            [
                'dsn' => [
                    'ReadableText' => 'This is a test DSN',
                    'Recipients' => [
                        [
                            'EmailAddress' => 'nadil@hyvor.com',
                            'Status' => '5.1.1',
                            'Action' => 'failed',
                        ],
                        [
                            'EmailAddress' => 'supun@hyvor.com',
                            'Status' => '5.1.1',
                            'Action' => 'failed',
                        ],
                    ]
                ]
            ]
        );
    }

}
