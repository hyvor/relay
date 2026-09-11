import type { Domain } from "../../types";
import consoleApi from "../consoleApi.svelte";

export function getDomains(search?: string, limit: number = 50, offset: number = 0) {
	const data: Record<string, any> = {
		limit,
		offset
	};
	
	if (search) {
		data.search = search;
	}

	return consoleApi.get<Domain[]>({
		endpoint: 'domains',
		data
	});
}

export function createDomain(domain: string, dkimSelector?: string, dkimPrivateKey?: string) {
	const data: Record<string, any> = { domain };
	if (dkimSelector) data.dkim_selector = dkimSelector;
	if (dkimPrivateKey) data.dkim_private_key = dkimPrivateKey;
	return consoleApi.post<Domain>({
		endpoint: 'domains',
		data
	});
}

export function deleteDomain(id: number) {
	return consoleApi.delete<void>({
		endpoint: `domains`,
		data: {
			id
		}
	});
}

export function verifyDomain(domain: string) {
	return consoleApi.post<Domain>({
		endpoint: `domains/verify`,
		data: {
			domain
		}
	});
} 
