import type { Suppression } from "../../types";
import consoleApi from "../consoleApi";

export function getSuppressions(email?: string | null) {
	return consoleApi.get<Suppression[]>({
		endpoint: 'suppressions',
		data: email ? { email } : {}
	});
}

export function deleteSuppression(id: number) {
	return consoleApi.delete<void>({
		endpoint: `suppressions/${id}`
	});
} 