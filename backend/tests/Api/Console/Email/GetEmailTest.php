<?php

namespace App\Tests\Api\Console\Email;

use App\Api\Console\Controller\EmailController;
use App\Api\Console\Object\SendObject;
use App\Service\Email\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EmailController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
class GetEmailTest extends WebTestCase
{
    public function test_get_specific_email(): void
    {
        $project = ProjectFactory::createOne();

        $domain = DomainFactory::createOne();

        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne(
            [
                'project' => $project,
                'domain' => $domain,
                'queue' => $queue,
            ]
        );

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails/' . $send->getId()
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($send->getId(), $json['id']);
    }

    public function test_get_specific_email_not_found(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/emails/999'
        );

        $this->assertSame(404, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
    }
}