export interface AppConfig {
	hyvor: {
		instance: string;
	};

	app: {
		webhook: {
            'events': string[];
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

export type ApiKeyScope = 'send_email' | 'full';

export type ApiKey = {
    id: number;
    name: string;
    scope: ApiKeyScope;
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
