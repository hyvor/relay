import type { Suppression, SuppressionReason } from "../../types";
import consoleApi from "../consoleApi.svelte";

export function getSuppressions(
	email?: string | null, 
	reason?: SuppressionReason | null,
	limit?: number,
	offset?: number
) {
	const data: Record<string, string | number> = {};
	
	if (email) {
		data.email = email;
	}
	
	if (reason) {
		data.reason = reason;
	}

	if (limit !== undefined) {
		data.limit = limit;
	}

	if (offset !== undefined) {
		data.offset = offset;
	}

	return consoleApi.get<Suppression[]>({
		endpoint: 'suppressions',
		data: Object.keys(data).length > 0 ? data : {}
	});
}

export function deleteSuppression(id: number) {
	return consoleApi.delete<void>({
		endpoint: `suppressions/${id}`
	});
} 