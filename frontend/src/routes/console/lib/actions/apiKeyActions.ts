import type { ApiKey } from "../../types";
import consoleApi from "../consoleApi.svelte";

export function getApiKeys() {
	return consoleApi.get<ApiKey[]>({
		endpoint: 'api-keys'
	});
}

export function createApiKey(name: string, scopes: string[], allowed_ips: string[]) {
	return consoleApi.post<ApiKey>({
		endpoint: 'api-keys',
		data: {
			name,
			scopes,
			allowed_ips
		}
	});
}

export function updateApiKey(id: number, data: { name?: string; scopes?: string[]; is_enabled?: boolean; allowed_ips?: string[] }) {
	return consoleApi.patch<ApiKey>({
		endpoint: `api-keys/${id}`,
		data
	});
}

export function deleteApiKey(id: number) {
	return consoleApi.delete<void>({
		endpoint: `api-keys/${id}`
	});
} 