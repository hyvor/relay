import type { SendAttempt, SendFeedback } from "../../../../types";


export type Event = {
    timestamp: number;
    type: 'queued' | 'attempt' | 'feedback';
    recipients_count?: number; // for queued
    attempt?: SendAttempt;
    feedback?: SendFeedback;
};