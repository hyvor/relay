import type { AnalyticsStats } from "../../types";
import consoleApi from "../consoleApi.svelte";


export function getAnalyticsStats() {
	return consoleApi.get<AnalyticsStats>({
		endpoint: 'analytics/stats'
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