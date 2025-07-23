<?php

namespace App\Service\Management\Health;

enum HealthCheckType: string
{
    case ALL_QUEUES_HAVE_AT_LEAST_ONE_IP = 'all_queues_have_at_least_one_ip';
    case ALL_ACTIVE_IPS_HAVE_CORRECT_PTR = 'all_active_ips_have_correct_ptr';

    /**
     * @return class-string<HealthCheckAbstract>
     */
    public function getClass(): string
    {
        return match ($this) {
            self::ALL_QUEUES_HAVE_AT_LEAST_ONE_IP => AllQueuesHaveAtLeastOneIpHealthCheck::class,
            self::ALL_ACTIVE_IPS_HAVE_CORRECT_PTR => AllActiveIpsHaveCorrectPtrHealthCheck::class,
        };
    }
}