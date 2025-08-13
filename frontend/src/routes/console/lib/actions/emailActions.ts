import type { Email, SendStatus } from "../../types";
import consoleApi from "../consoleApi.svelte";

export function getSends(
	status: SendStatus | null,
	from_search : string | null,
    to_search : string | null,
	limit: number,
	offset: number
) {
	return consoleApi.get<Email[]>({
		endpoint: 'sends',
		data: {
			status,
			from_search,
            to_search,
			limit,
			offset
		}
	});
}

export function getEmailByUuid(uuid: string) {
	return consoleApi.get<Email>({
		endpoint: `sends/uuid/${uuid}`
	});
}
