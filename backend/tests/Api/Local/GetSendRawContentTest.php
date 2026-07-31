<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Api\Local\LocalAuthorizationListener;
use App\Service\Send\SendContentStorage;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;

#[CoversClass(LocalController::class)]
#[CoversClass(LocalAuthorizationListener::class)]
class GetSendRawContentTest extends WebTestCase
{
    public function test_get_send_raw_content(): void
    {
        $storage = $this->container->get(SendContentStorage::class);
        $this->assertInstanceOf(SendContentStorage::class, $storage);

        $uuid = (string) Uuid::v4();
        $rawEmail = "From: sender@example.com\r\nSubject: Test\r\n\r\nHello World";
        $storage->store($uuid, $rawEmail);

        $response = $this->localApi(
            'GET',
            '/sends/' . $uuid . '/raw'
        );

        $this->assertResponseIsSuccessful();
        $json = $this->getJson();
        $this->assertSame($rawEmail, $json['raw']);
    }

    public function test_get_send_raw_content_not_found(): void
    {
        $uuid = (string) Uuid::v4();

        $response = $this->localApi(
            'GET',
            '/sends/' . $uuid . '/raw'
        );

        $this->assertResponseStatusCodeSame(404);
        $json = $this->getJson();
        $this->assertSame("Raw content for send with UUID $uuid not found", $json['message']);
    }
}
