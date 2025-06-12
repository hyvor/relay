
export interface Server {
    id: number;
    created_at: number;
    hostname: string;
    last_ping_at?: number | null;
    api_on: boolean;
    email_on: boolean;
    webhook_on: boolean;
}

export interface IpAddress {
    id: number;
    created_at: number;
    server_id: number;
    ip_address: string;
    email_queue: string | null;
    is_active: boolean;
    is_enabled: boolean;
}

export interface Queue {
    id: number;
    created_at: number;
    name: string;
}