import type { Domain } from "../../types";
import consoleApi from "../consoleApi";

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

export function createDomain(domain: string) {
	return consoleApi.post<Domain>({
		endpoint: 'domains',
		data: {
			domain
		}
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

export function verifyDomain(id: number, domain: string) {
	return consoleApi.post<Domain>({
		endpoint: `domains/${id}/verify`,
		data: {
			domain
		}
	});
} 