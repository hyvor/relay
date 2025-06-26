

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
}

export interface SmtpConversation {
    StartTime: string;
    Steps: SmtpStep[];
}

export interface SmtpStep {
    Name: 'dial' | 'helo' | 'mail' | 'rcpt' | 'data' | 'quit';
    Duration: string;
    ReplyCode: number
    ReplyText: string;
}