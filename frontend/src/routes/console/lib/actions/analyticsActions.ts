import type { AnalyticsStats } from "../../types";
import consoleApi from "../consoleApi";


export function getAnalyticsStats() {
	return consoleApi.get<AnalyticsStats>({
		endpoint: 'analytics/stats'
	});
}