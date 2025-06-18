export type Project = {
    id: string;
    name: string;
    createdAt: string;
}

export type EmailStatus = 'queued' | 'sent' | 'failed';

export type Email = {
    id: number;
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
}