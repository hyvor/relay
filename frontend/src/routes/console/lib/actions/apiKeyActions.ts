import type { ApiKey } from "../../types";
import consoleApi from "../consoleApi";

export function getApiKeys() {
	return consoleApi.get<ApiKey[]>({
		endpoint: 'api-keys'
	});
}

export function createApiKey(name: string, scopes: string[]) {
	return consoleApi.post<ApiKey>({
		endpoint: 'api-keys',
		data: {
			name,
			scopes
		}
	});
}

export function updateApiKey(id: number, data: { name?: string; scopes?: string[]; enabled?: boolean }) {
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