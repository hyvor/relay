
export interface SudoInitResponse {
    config: SudoConfig,
    instance: Instance,
}

export interface SudoConfig {
    app_version: string;
    instance: string;
}

export interface Instance {
    domain: string;
    dkim_host: string;
    dkim_txt_value: string;
    private_network_cidr: string;
}

export interface Server {
    id: number;
    created_at: number;
    hostname: string;
    private_ip: string | null;
    last_ping_at?: number | null;
    is_alive: boolean;
    api_workers: number;
    email_workers: number;
    webhook_workers: number;
    incoming_workers: number;
}

export interface IpAddress {
    id: number;
    created_at: number;
    server_id: number;
    ip_address: string;
    ptr: string;
    queue: Queue | null;
    is_ptr_forward_valid: boolean;
    is_ptr_reverse_valid: boolean;
}

export interface Queue {
    id: number;
    created_at: number;
    name: string;
}

export interface HealthCheckResult<T extends HealthCheckName = HealthCheckName> {
    passed: boolean;
    data: HealthCheckData[T];
    checked_at: string;
}

export interface HealthCheckData {
    all_active_ips_have_correct_ptr: {
        invalid_ptrs: Array<{
            ip: string;
            forward_valid: boolean;
            reverse_valid: boolean;
        }>;
    },
    all_queues_have_at_least_one_ip: {
        queues_without_ip: string[];
    },
    instance_dkim_correct: {
        error: string;
        expected?: string;
        actual?: string;
    },
    all_ips_are_in_spf_record: {
        invalid_ips: string[];
    },
    all_servers_can_be_reached_via_private_network: {
        unreachable_servers: string[];
    }
}

export type HealthCheckName = keyof HealthCheckData;

export interface HealthCheckResults {
    last_checked_at: number | null;
    results: {
        [key in HealthCheckName]: HealthCheckResult<key>;
    };
}

// DNS Records
export interface DnsRecord {
    id: number;
    created_at: number;
    updated_at: number;
    type: DnsRecordType;
    subdomain: string;
    content: string;
    ttl: number;
    priority: number;
}

export interface DefaultDnsRecord {
    type: DnsRecordType;
    host: string;
    content: string;
    ttl: number;
    priority: number;
}

export type DnsRecordType = 'A' | 'AAAA' | 'CNAME' | 'MX' | 'TXT';

// Debug

export interface DebugIncomingEmail {
    id: number;
    created_at: number;
    type: "bounce" | "fbl";
    status: "success" | "failed";
    raw_email: string;
    mail_from: string;
    rcpt_to: string;
    parsed_data?: Record<string, any> | null;
    error_message?: string | null;
}