import type { SendAttempt, SendAttemptRecipient, SendFeedback } from "../../../../types";


export type Event = {
    timestamp: number;
    type: 'queued' | 'suppressed' | 'attempt' | 'feedback';
    recipients_count?: number; // for queued
    suppressed_recipients?: string[];
    attempt?: {
        attempt: SendAttempt,
        recipient: SendAttemptRecipient;
    };
    feedback?: SendFeedback;
};