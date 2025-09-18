<script lang="ts">
	import { onMount } from 'svelte';
	import { getAnalyticsStats } from '../../lib/actions/analyticsActions';
	import type { AnalyticsStats } from '../../types';
	import { IconMessage, Loader, Button } from '@hyvor/design/components';
	import Stat from './Stat.svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	let stats: AnalyticsStats = $state({} as AnalyticsStats);
	let statsLoading = $state(true);
	let statsError = $state('');
	let selectedPeriod: '30d' | '7d' | '24h' = $state('30d');

	onMount(() => {
		loadStats();
	});

	function loadStats() {
		statsLoading = true;
		statsError = '';
		
		getAnalyticsStats(selectedPeriod)
			.then((res) => {
				stats = res;
			})
			.catch((err) => {
				statsError = err.message || 'Failed to load analytics stats';
			})
			.finally(() => {
				statsLoading = false;
			});
	}

	function setPeriod(period: '30d' | '7d' | '24h') {
		selectedPeriod = period;
		loadStats();
	}

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

	function getPeriodLabel(period: string): string {
		const labels = {
			'30d': 'Last 30 days',
			'7d': 'Last 7 days',
			'24h': 'Last 24 hours'
		};
		return labels[period as keyof typeof labels] || 'Last 30 days';
	}
</script>

<div class="stats-wrap">
	<div class="period-buttons">
		<Button 
			size="x-small"
			variant={selectedPeriod === '30d' ? 'fill' : 'outline'}
			onclick={() => setPeriod('30d')}
		>
			30d
		</Button>
		<Button 
			size="x-small"
			variant={selectedPeriod === '7d' ? 'fill' : 'outline'}
			onclick={() => setPeriod('7d')}
		>
			7d
		</Button>
		<Button 
			size="x-small"
			variant={selectedPeriod === '24h' ? 'fill' : 'outline'}
			onclick={() => setPeriod('24h')}
		>
			24h
		</Button>
	</div>

	{#if statsLoading}
		<Loader block padding={55} />
	{:else if statsError}
		<IconMessage error message={statsError} />
	{:else}
		<div class="stats">
			<Stat>
				{#snippet title()}
					Sends <span class="period-label">({getPeriodLabel(selectedPeriod)})</span>
				{/snippet}
				{stats.sends_stats || 0}
			</Stat>
			<Stat>
				{#snippet title()}
					Bounce Rate <span class="period-label">({getPeriodLabel(selectedPeriod)})</span>
				{/snippet}
				<span style="color: {getRateColor(stats.bounce_rate_stats || 0, 'bounce')}">
					{roundToTwoDecimalPlaces((stats.bounce_rate_stats || 0) * 100)}%
				</span>
			</Stat>
			<Stat>
				{#snippet title()}
					Complaint Rate <span class="period-label">({getPeriodLabel(selectedPeriod)})</span>
				{/snippet}
				<span style="color: {getRateColor(stats.complaint_rate_stats || 0, 'complaint')}">
					{roundToTwoDecimalPlaces((stats.complaint_rate_stats || 0) * 100)}%
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
	
	.period-buttons {
		display: flex;
		gap: 8px;
		padding: 20px 50px 0;
		justify-content: flex-start;
	}
	
	.stats {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 16px;
		padding: 30px 50px;
	}
	
	.period-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: normal;
		margin-left: 3px;
	}
</style>
