
export interface Server {
    id: number;
    created_at: number;
    hostname: string;
    last_ping_at?: number | null;
    api_on: boolean;
    email_on: boolean;
    webhook_on: boolean;
}