<script lang="ts">
	import { onMount } from 'svelte';
	import { getAnalyticsStats } from '../../lib/actions/analyticsActions';
	import type { AnalyticsStats } from '../../types';
	import { IconMessage, Loader } from '@hyvor/design/components';
	import Stat from './Stat.svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	let stats: AnalyticsStats = $state({} as AnalyticsStats);
	let statsLoading = $state(true);
	let statsError = $state('');

	onMount(() => {
		getAnalyticsStats()
			.then((res) => {
				stats = res;
			})
			.catch((err) => {
				statsError = err.message || 'Failed to load analytics stats';
			})
			.finally(() => {
				statsLoading = false;
			});
	});

	function roundToTwoDecimalPlaces(value: number): number {
		return Math.round(value * 100) / 100;
	}

	function getRateColor(value: number, type: 'bounce' | 'complaint'): string {
		const config = getAppConfig().app.compliance.rates;
		const error = type === 'bounce' ? config.bounce_rate_error : config.complaint_rate_error;
		const warning =
			type === 'bounce' ? config.bounce_rate_warning : config.complaint_rate_warning;

		if (value >= error) {
			return 'var(--red)';
		} else if (value >= warning) {
			return 'var(--orange)';
		} else {
			return 'inherit';
		}
	}
</script>

<div class="stats-wrap">
	{#if statsLoading}
		<Loader block padding={55} />
	{:else if statsError}
		<IconMessage error message={statsError} />
	{:else}
		<div class="stats">
			<Stat>
				{#snippet title()}
					Sends <span class="d30">(Last 30d)</span>
				{/snippet}
				{stats.sends_30d}
			</Stat>
			<Stat>
				{#snippet title()}
					Bounce Rate <span class="d30">(Last 30d)</span>
				{/snippet}
				<span style="color: {getRateColor(stats.bounce_rate_30d, 'bounce')}">
					{roundToTwoDecimalPlaces(stats.bounce_rate_30d * 100)}%
				</span>
			</Stat>
			<Stat>
				{#snippet title()}
					Complaint Rate <span class="d30">(Last 30d)</span>
				{/snippet}
				<span style="color: {getRateColor(stats.complaint_rate_30d, 'complaint')}">
					{roundToTwoDecimalPlaces(stats.complaint_rate_30d * 100)}%
				</span>
			</Stat>
		</div>
	{/if}
</div>

<style>
	.stats-wrap {
		border-bottom: 1px solid var(--border);
		max-height: 300px;
	}
	.stats {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 16px;
		padding: 30px 50px;
	}
	.d30 {
		font-size: 12px;
		color: var(--text-light);
		font-weight: normal;
		margin-left: 3px;
	}
</style>
