

export type Event = {
    timestamp: number;
    type: 'queued' | 'accepted' | 'deferred' | 'bounced' | 'complaint';
    recipient_name?: string;
    recipient_address?: string;
    recipients_count?: number;
};