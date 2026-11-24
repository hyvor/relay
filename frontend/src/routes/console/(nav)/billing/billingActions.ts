import consoleApi from '../../lib/consoleApi.svelte';

export interface Usage {
	used: number;
	limit: number;
}

export interface UsageData {
	emails: Usage;
	projects: Usage;
	domains: Usage;
}

export function getUsage() {
	return consoleApi.get<UsageData>({
		endpoint: 'billing/usage',
		userApi: true
	});
}
