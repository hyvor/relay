import type { Suppression, SuppressionReason } from "../../types";
import consoleApi from "../consoleApi";

export function getSuppressions(email?: string | null, reason?: SuppressionReason | null) {
	const data: Record<string, string> = {};
	
	if (email) {
		data.email = email;
	}
	
	if (reason) {
		data.reason = reason;
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