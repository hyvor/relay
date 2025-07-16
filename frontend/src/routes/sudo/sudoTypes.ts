
export interface Server {
    id: number;
    created_at: number;
    hostname: string;
    last_ping_at?: number | null;
    is_alive: boolean;
    api_on: boolean;
    api_workers: number;
    email_workers: number;
    webhook_workers: number;
}

export interface IpAddress {
    id: number;
    created_at: number;
    server_id: number;
    ip_address: string;
    ptr: string;
    queue: Queue | null;
    is_active: boolean;
    is_enabled: boolean;
}

export interface Queue {
    id: number;
    created_at: number;
    name: string;
}

export interface HealthCheckResult {
    passed: boolean;
    data: any;
    checked_at: string;
}

export interface HealthCheckQueueData {
    queues_without_ip: string[];
}

export interface HealthCheckPtrData {
    invalid_ptrs: Array<{
        ip: string;
        forward_valid: boolean;
        reverse_valid: boolean;
    }>;
}

export interface HealthCheckResults {
    last_checked_at: number | null;
    results: {
        all_active_ips_have_correct_ptr: HealthCheckResult;
        all_queues_have_at_least_one_ip: HealthCheckResult;
    };
}
