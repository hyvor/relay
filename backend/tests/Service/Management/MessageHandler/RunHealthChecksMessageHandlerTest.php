<?php

namespace App\Tests\Service\Management\MessageHandler;

use App\Service\Management\Health\HealthCheckService;
use App\Service\Management\Message\RunHealthChecksMessage;
use App\Service\Management\MessageHandler\RunHealthChecksMessageHandler;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RunHealthChecksMessage::class)]
#[CoversClass(RunHealthChecksMessageHandler::class)]
class RunHealthChecksMessageHandlerTest extends KernelTestCase
{

    public function test_calls_health_checks(): void
    {
        $logger = $this->getTestLogger();

        $healthCheckService = $this->createMock(HealthCheckService::class);
        $healthCheckService->expects($this->once())
            ->method('runAllHealthChecks');
        $this->container->set(HealthCheckService::class, $healthCheckService);

        $message = new RunHealthChecksMessage();

        $transport = $this->transport('scheduler_server');
        $transport->send($message);
        $transport->throwExceptions()->process();

        $logger->hasInfoThatContains("Running health checks");
        $logger->hasInfoThatContains("Health checks completed successfully");
    }

}