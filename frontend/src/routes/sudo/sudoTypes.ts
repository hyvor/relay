
export interface SudoInitResponse {
    config: SudoConfig
}

export interface SudoConfig {
    app_version: string;
    instance: string;
}

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