<script lang="ts">
	import { onMount } from 'svelte';
	import {
		getAnalyticsSendsChart,
		type AnalyticsSendChartRow
	} from '../../lib/actions/analyticsActions';
	import { IconMessage, Loader, toast } from '@hyvor/design/components';
	import SendsChart from './SendsChart.svelte';

	let data: AnalyticsSendChartRow[] = $state([]);
	let loading = $state(true);
	let error = $state('');

	onMount(() => {
		getAnalyticsSendsChart()
			.then((res) => {
				data = res;
			})
			.catch((err) => {
				error = err.message || 'Failed to load sends chart data';
			})
			.finally(() => {
				loading = false;
			});
	});
</script>

{#if loading}
	<Loader full />
{:else if error}
	<IconMessage error message={error} />
{:else}
	<div class="chart-wrap">
		<SendsChart {data} />
	</div>
{/if}

<style>
	.chart-wrap {
		width: 100%;
		max-height: 500px;
		padding: 60px 40px;
		overflow: hidden;
	}
</style>
