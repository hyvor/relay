<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendContentObject;
use App\Service\Send\Dto\SendContent;
use App\Service\Send\SendContentStorage;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendContentObject::class)]
#[CoversClass(SendContentStorage::class)]
#[CoversClass(SendContent::class)]
class GetSendContentByUuidTest extends WebTestCase
{
    private function storeContent(string $uuid): void
    {
        $storage = $this->container->get(SendContentStorage::class);
		$this->assertInstanceOf(SendContentStorage::class, $storage);

        $storage->store(
            $uuid,
            new SendContent(
                raw: 'raw-mime-content',
                bodyHtml: '<p>Hello</p>',
                bodyText: 'Hello',
                headers: ['X-Custom' => 'value'],
            )
        );
    }

    public function test_get_content(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);

        $this->storeContent($send->getUuid());

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/uuid/' . $send->getUuid() . '/content',
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('<p>Hello</p>', $json['body_html']);
        $this->assertSame('Hello', $json['body_text']);
        $this->assertSame('raw-mime-content', $json['raw']);
        $this->assertSame(['X-Custom' => 'value'], $json['headers']);
    }

    public function test_content_not_found_when_not_stored(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/uuid/' . $send->getUuid() . '/content',
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_send_not_found(): void
    {
        $project = ProjectFactory::createOne();
        $uuid = Uuid::v4();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/uuid/' . $uuid . '/content',
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(404, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame("Send with UUID " . $uuid . " not found", $json['message']);
    }

    public function test_cannot_get_other_project_content(): void
    {
        $project = ProjectFactory::createOne();
        $otherProject = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);

        $this->storeContent($send->getUuid());

        $response = $this->consoleApi(
            $otherProject,
            'GET',
            '/sends/uuid/' . $send->getUuid() . '/content',
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(400, $response->getStatusCode());
    }
}
