<?php

namespace App\Service\Management\Health;

use App\Entity\Instance;
use App\Service\Instance\InstanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

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
            $healthCheckType = $this->getHealthCheckType($healthCheck);
            
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

    /**
     * Get the health check type string from the health check instance
     */
    private function getHealthCheckType(HealthCheckAbstract $healthCheck): string
    {
        $className = get_class($healthCheck);
        
        foreach (HealthCheckType::cases() as $type) {
            if ($type->getClass() === $className) {
                return $type->value;
            }
        }
        
        // Fallback to class name if not found in enum
        return $className;
    }
} 