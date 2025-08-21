<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Api\Local\Input\DsnInput;
use App\Api\Local\Input\DsnRecipientsInput;
use App\Api\Local\Input\IncomingBounceInput;
use App\Entity\Suppression;
use App\Entity\Type\SuppressionReason;
use App\Service\IncomingMail\IncomingMailService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
#[CoversClass(IncomingMailService::class)]
#[CoversClass(IncomingBounceInput::class)]
#[CoversClass(DsnInput::class)]
#[CoversClass(DsnRecipientsInput::class)]
class IncomingBounceTest extends WebTestCase
{
    public function test_incoming_bounce(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project
        ]);

        $response = $this->localApi(
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
                ],
                'bounce_uuid' => $send->getUuid()
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $suppressions = $this->em->getRepository(Suppression::class)->findBy([
            'project' => $project->_real(),
            'reason' => SuppressionReason::BOUNCE
        ]);

        $this->assertSame(2, count($suppressions));
        $this->assertSame('nadil@hyvor.com', $suppressions[0]->getEmail());
        $this->assertSame(SuppressionReason::BOUNCE, $suppressions[0]->getReason());
        $this->assertSame('This is a test DSN', $suppressions[0]->getDescription());
        $this->assertSame('supun@hyvor.com', $suppressions[1]->getEmail());
        $this->assertSame(SuppressionReason::BOUNCE, $suppressions[1]->getReason());
        $this->assertSame('This is a test DSN', $suppressions[1]->getDescription());
    }

}
