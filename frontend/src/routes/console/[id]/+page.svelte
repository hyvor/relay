<script lang="ts">
	import { onMount } from 'svelte';
	import SingleBox from '../@components/content/SingleBox.svelte';
	import { getAnalyticsStats } from '../lib/actions/analyticsActions';
	import type { AnalyticsStats } from '../types';
	import Stats from './overview/Stats.svelte';
	import SendsChart from './overview/SendsChartView.svelte';

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
</script>

<SingleBox style="overflow-auto">
	<Stats />
	<SendsChart />
</SingleBox>
