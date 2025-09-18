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
    user: ProjectUserMiniObject;
    oidc_sub: string | null;
}

export type Project = {
    id: number;
    name: string;
    created_at: string;
    send_type: ProjectSendType;
}

export type ProjectSendType = 'transactional' | 'distributional';

export type Send = {
    id: number;
    uuid: string;
    created_at: number;
    from_address: string;
    from_name: string | null;
    subject: string | null;
    body_html: string | null;
    body_text: string | null;
    raw: string;
    size_bytes: number;
    queued: boolean;
    send_after: number;

    recipients: SendRecipient[];
    attempts: SendAttempt[];
    feedback: SendFeedback[];
}

export type SendRecipientStatus = 'queued' | 'accepted' | 'deferred' | 'bounced' | 'failed' | 'complained';

export interface SendRecipient {
    id: number;
    type: 'to' | 'cc' | 'bcc';
    address: string;
    name: string;
    status: SendRecipientStatus;
    is_suppressed: boolean;
}

export interface SendAttempt {
    created_at: number;
    status: 'accepted' | 'deferred' | 'bounced' | 'failed';
    try_count: number;
    domain: string;
    resolved_mx_hosts: string[];
    responded_mx_host: string | null;
    smtp_conversations: Record<string, SmtpConversation>;
    recipient_ids: number[];
    duration_ms: number;
    error: string | null;
}

export interface SendFeedback {
    id: number;
    created_at: number;
    type: 'bounce' | 'complaint';
    recipient_id: number;
    debug_incoming_email_id: number;
}

export interface SmtpConversation {
    start_time: string;
    network_error: string; // empty if no error
    smtp_error: {
        code: number;
        message: string;
    } | null;
    steps: SmtpStep[];
}

export interface SmtpStep {
    name: 'dial' | 'helo' | 'mail' | 'rcpt' | 'data' | 'data_close' | 'quit';
    duration: string;
    command: string;
    reply_code: number
    reply_text: string;
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
    sends_stats: number;
    bounce_rate_stats: number;
    complaint_rate_stats: number;
}

export interface ProjectUserMiniObject {
    id: number;
    name: string;
    email: string;
    username: string | null;
    picture_url: string | null;
    oidc_sub: string | null;
}
