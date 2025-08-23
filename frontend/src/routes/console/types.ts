export interface AppConfig {
    hosting: 'self' | 'cloud';

	hyvor: {
		instance: string;
	};

    user: {
        id: number;
        name: string;
        email: string;
        picture_url: string | null;
    }

	app: {
        system_project_id: number;
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


export type Scope = 
    'project.read' |
    'project.write' |
    'sends.read' |
    'sends.write' |
    'sends.send' |
    'domains.read' |
    'domains.write' |
    'webhooks.read' |
    'webhooks.write' |
    'api_keys.read' |
    'api_keys.write' |
    'suppressions.read' |
    'suppressions.write' |
    'analytics.read';

export interface ProjectUser {
    id: number;
	created_at: number;
    scopes: Scope[];
    project: Project;
	user: {
		id: number;
		name: string;
		email: string;
		username: string | null;
		picture_url: string | null;
	};
	oidc_sub: string | null;
}

export type Project = {
    id: number;
    name: string;
    created_at: string;
    send_type: ProjectSendType;
}

export type ProjectSendType = 'transactional' | 'distributional';

export type SendStatus = 'queued' | 'accepted' | 'bounced' | 'complained';

export type Email = {
    id: number;
    uuid: string;
    created_at : number;
    accepted_at?: number;
    bounced_at?: number;
    status: SendStatus;
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
    status: 'accepted' | 'deferred' | 'bounced';
    try_count: number;
    resolved_mx_hosts: string[];
    accepted_mx_host: string | null;
    smtp_conversations: Record<string, SmtpConversation>;
    error: string | null;
}

export interface SmtpConversation {
    StartTime: string;
    Error: string; // empty if no error
    SmtpErrorStatus: number;
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
    status: 'pending' | 'active' | 'warning' | 'suspended';
    dkim_selector: string;
    dkim_host: string;
    dkim_public_key: string;
    dkim_txt_value: string;
    dkim_checked_at?: number;
    dkim_error_message?: string;
}


export interface AnalyticsStats {
    sends_30d: number;
    bounce_rate_30d: number;
    complaint_rate_30d: number;
}

export interface ProjectUserSearchResult {
	id: number;
	email: string;
	name: string;
	picture_url: string | null;
	oidc_sub: string | null;
}
