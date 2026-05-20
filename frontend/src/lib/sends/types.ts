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

	project: {
		id: number;
		name: string;
	};
};

export type SendRecipientStatus =
	| 'queued'
	| 'accepted'
	| 'deferred'
	| 'bounced'
	| 'suppressed'
	| 'failed'
	| 'complained';

export type SendRecipientStatusForAttempt = Omit<SendRecipientStatus, 'queued' | 'suppressed' | 'complained'>;

export interface SendRecipient {
	id: number;
	type: 'to' | 'cc' | 'bcc';
	address: string;
	name: string;
	status: SendRecipientStatus;
}

export type SendAttemptStatus = 'accepted' | 'deferred' | 'bounced' | 'failed' | 'partial';

export interface SendAttempt {
	created_at: number;
	status: SendAttemptStatus;
	try_count: number;
	domain: string;
	resolved_mx_hosts: string[];
	responded_mx_host: string | null;
	smtp_conversations: Record<string, SmtpConversation>;
	duration_ms: number;
	recipients: SendAttemptRecipient[];
}

export interface SendAttemptRecipient {
	id: number;
	created_at: number;
	recipient_id: number;
	recipient_status: SendRecipientStatusForAttempt;
	smtp_code: number;
	smtp_enhanced_code: string;
	smtp_message: string;
	is_suppressed: boolean;
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
	network_error: string;
	steps: SmtpStep[];
}

export interface SmtpStep {
	name: 'dial' | 'helo' | 'mail' | 'rcpt' | 'data' | 'data_close' | 'quit';
	duration: string;
	command: string;
	reply_code: number;
	reply_text: string;
}

export type DateFilterPreset = 'today' | 'yesterday' | 'this_week' | 'custom' | null;

export interface StatusOption {
	value: SendRecipientStatus | null;
	label: string;
}

export type RetrySendFn = (
	sendId: number,
	sendAfter?: number,
	recipientIds?: number[]
) => Promise<{ retried_recipients: number; send: Send }>;
