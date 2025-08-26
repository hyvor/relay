import type { SendAttempt } from "../../../../types";


export type Event = {
    timestamp: number;
    type: 'queued' | 'attempt' | 'feedback';
    recipients_count?: number; // for queued
    attempt?: SendAttempt;
};