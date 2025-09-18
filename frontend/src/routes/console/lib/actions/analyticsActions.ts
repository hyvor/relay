import type { AnalyticsStats } from "../../types";
import consoleApi from "../consoleApi.svelte";


export function getAnalyticsStats(period: '30d' | '7d' | '24h' = '30d') {
	return consoleApi.get<AnalyticsStats>({
		endpoint: 'analytics/stats',
		params: { period }
	});
}

export interface AnalyticsSendChartRow {
	date: string;
	total: number;
	accepted: number;
	bounced: number;
	complained: number;
	queued: number;
}

export function getAnalyticsSendsChart() {
	return consoleApi.get<AnalyticsSendChartRow[]>({
		endpoint: 'analytics/sends/chart'
	});
}