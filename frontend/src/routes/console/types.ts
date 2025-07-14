export interface AppConfig {
	hyvor: {
		instance: string;
	};

	app: {
		webhook: {
            'events': string[];
        },
        api_keys: {
            scopes: string[];
        },
        compliance: {
            rates: {
                bounce_rate_warning: number; // 0.02 for 2%
                bounce_rate_error: number;
                complaint_rate_warning: number; // 0.05 for 5%
                complaint_rate_error: number;
            }
        }
	};
}

export type Project = {
    id: string;
    name: string;
    createdAt: string;
}

export type EmailStatus = 'queued' | 'sent' | 'failed';

export type Email = {
    id: number;
    uuid: string;
    created_at : number;
    sent_at?: number;
    failed_at?: number;
    status: EmailStatus;
    from_address: string;
    to_address: string;
    subject?: string;
    body_html?: string;
    body_text?: string;
    raw: string;
    attempts: SendAttempt[];
}

export interface SendAttempt {
    created_at: number;
    status: 'sent' | 'failed' | 'queued';
    try_count: number;
    resolved_mx_hosts: string[];
    sent_mx_host: string | null;
    smtp_conversations: Record<string, SmtpConversation>;
    error: string | null;
}

export interface SmtpConversation {
    StartTime: string;
    Steps: SmtpStep[];
}

export interface SmtpStep {
    Name: 'dial' | 'helo' | 'mail' | 'rcpt' | 'data' | 'data_close' | 'quit';
    Duration: string;
    Command: string;
    ReplyCode: number
    ReplyText: string;
}

export type ApiKey = {
    id: number;
    name: string;
    scopes: string[];
    key?: string;
    created_at: number;
    is_enabled: boolean;
    last_accessed_at?: number;
}

export type Webhook = {
    id: number;
    url: string;
    description: string;
    events: string[];
}

export type WebhookDeliveryStatus = 'pending' | 'delivered' | 'failed';

export type WebhookDelivery = {
    id: number;
    url: string;
    event: string;
    status: WebhookDeliveryStatus;
    response: string;
    created_at: number;
}

export type SuppressionReason = 'bounce' | 'complaint';

export type Suppression = {
    id: number;
    email: string;
    reason: SuppressionReason;
    description: string | null;
    created_at: number;
}

export type Domain = {
    id: number;
    created_at: number;
    domain: string;
    dkim_selector: string;
    dkim_host: string;
    dkim_txt_name: string;
    dkim_public_key: string;
    dkim_txt_value: string;
    dkim_verified: boolean;
    dkim_checked_at?: number;
    dkim_error_message?: string;
}


export interface AnalyticsStats {
    sends_30d: number;
    bounce_rate_30d: number;
    complaint_rate_30d: number;
}
