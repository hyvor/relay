<?php

namespace App\Service\Management\Health;

use App\Service\Instance\InstanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\String\ByteString;

class HealthCheckService
{
    use ClockAwareTrait;

    /**
     * @param iterable<HealthCheckAbstract> $healthChecks
     */
    public function __construct(
        private EntityManagerInterface $em,
        private InstanceService $instanceService,
        #[AutowireIterator('app.health_check')] private iterable $healthChecks
    ) {
    }

    /**
     * Run all health checks and save results to the instances table
     */
    public function runAllHealthChecks(): void
    {
        $instance = $this->instanceService->getInstance();
        
        $results = [];
        
        foreach ($this->healthChecks as $healthCheck) {
            $healthCheckType = $this->getHealthCheckName($healthCheck);
            
            $passed = $healthCheck->check();
            $data = $healthCheck->getData();
            
            $results[$healthCheckType] = [
                'passed' => $passed,
                'data' => $data,
                'checked_at' => $this->now()->format('c')
            ];
        }
        
        $instance->setHealthCheckResults($results);
        $instance->setLastHealthCheckAt($this->now());
        $instance->setUpdatedAt($this->now());
        
        $this->em->persist($instance);
        $this->em->flush();
    }

    // snake case name
    private function getHealthCheckName(HealthCheckAbstract $healthCheck): string
    {
        $className = get_class($healthCheck);
        $classParts = explode('\\', $className);
        $healthCheckType = end($classParts);
        $snake = new ByteString($healthCheckType)->snake();
        return str_replace('_health_check', '', $snake->toString());
    }
} 