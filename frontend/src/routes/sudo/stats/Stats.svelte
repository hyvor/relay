<script lang="ts">
	import { onMount } from 'svelte';
	import { getSudoStats } from '../sudoActions';
	import type { SudoStats } from '../sudoTypes';
	import { IconMessage, Loader, Button } from '@hyvor/design/components';
	import Stat from '../../console/[id]/overview/Stat.svelte';

	let stats: SudoStats = $state({} as SudoStats);
	let statsLoading = $state(true);
	let statsError = $state('');
	let selectedPeriod: '24h' | '7d' | '30d' = $state('24h');

	onMount(() => {
		loadStats();
	});

	function loadStats() {
		statsLoading = true;
		statsError = '';

		getSudoStats(selectedPeriod)
			.then((res) => {
				stats = res;
			})
			.catch((err) => {
				statsError = err.message || 'Failed to load stats';
			})
			.finally(() => {
				statsLoading = false;
			});
	}

	function setPeriod(period: '24h' | '7d' | '30d') {
		selectedPeriod = period;
		loadStats();
	}

	function roundToTwoDecimalPlaces(value: number): number {
		return Math.round(value * 100) / 100;
	}

	function getPeriodLabel(period: string): string {
		const labels = {
			'30d': 'Last 30 days',
			'7d': 'Last 7 days',
			'24h': 'Last 24 hours'
		};
		return labels[period as keyof typeof labels] || 'Last 24 hours';
	}

	function getRateColor(value: number): string {
		if (value >= 0.1) {
			return 'var(--red)';
		} else if (value >= 0.05) {
			return 'var(--orange)';
		} else {
			return 'inherit';
		}
	}
</script>

<div class="stats-wrap">
	<div class="period-buttons">
		<Button
			size="x-small"
			variant={selectedPeriod === '24h' ? 'fill' : 'outline'}
			onclick={() => setPeriod('24h')}
		>
			24h
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
			variant={selectedPeriod === '30d' ? 'fill' : 'outline'}
			onclick={() => setPeriod('30d')}
		>
			30d
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
					Projects <span class="period-label">({getPeriodLabel(selectedPeriod)})</span>
				{/snippet}
				{stats.project_count || 0}
			</Stat>
			<Stat>
				{#snippet title()}
					Sends <span class="period-label">({getPeriodLabel(selectedPeriod)})</span>
				{/snippet}
				{stats.sends || 0}
			</Stat>
			<Stat>
				{#snippet title()}
					Bounce Rate <span class="period-label">({getPeriodLabel(selectedPeriod)})</span>
				{/snippet}
				<span style="color: {getRateColor(stats.bounce_rate || 0)}">
					{roundToTwoDecimalPlaces((stats.bounce_rate || 0) * 100)}%
				</span>
			</Stat>
			<Stat>
				{#snippet title()}
					Complaint Rate <span class="period-label"
						>({getPeriodLabel(selectedPeriod)})</span
					>
				{/snippet}
				<span style="color: {getRateColor(stats.complaint_rate || 0)}">
					{roundToTwoDecimalPlaces((stats.complaint_rate || 0) * 100)}%
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
		gap: 5px;
		padding: 20px 50px 0;
		justify-content: flex-start;
	}

	.stats {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 16px;
		padding: 20px 50px 30px;
	}

	.period-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: normal;
		margin-left: 3px;
	}
</style>
