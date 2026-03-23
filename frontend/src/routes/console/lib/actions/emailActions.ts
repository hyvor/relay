import type { Send, SendRecipientStatus } from "../../types";
import consoleApi from "../consoleApi.svelte";

export function getSends(
	status: SendRecipientStatus | null,
	from_search: string | null,
	to_search: string | null,
	subject_search: string | null,
	date_from_search: string | null,
	date_to_search: string | null,
	limit: number,
	before_id: number | null
) {
	return consoleApi.get<Send[]>({
		endpoint: 'sends',
		data: {
			status,
			from_search,
			to_search,
			subject_search,
			date_from_search,
			date_to_search,
			limit,
			before_id: before_id
		}
	});
}

export function getEmailByUuid(uuid: string) {
	return consoleApi.get<Send>({
		endpoint: `sends/uuid/${uuid}`
	});
}

export function retrySend(sendId: number, sendAfter?: number, recipientIds?: number[]) {
	const data: Record<string, any> = {};
	if (sendAfter !== undefined) data.send_after = sendAfter;
	if (recipientIds !== undefined) data.recipient_ids = recipientIds;
	return consoleApi.post<{ retried_recipients: number; send: Send }>({
		endpoint: `sends/${sendId}/retry`,
		data
	});
}
