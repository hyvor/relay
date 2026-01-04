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
	offset: number
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
			offset
		}
	});
}

export function getEmailByUuid(uuid: string) {
	return consoleApi.get<Send>({
		endpoint: `sends/uuid/${uuid}`
	});
}
