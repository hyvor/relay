<script lang="ts">
	import SingleBox from '../@components/content/SingleBox.svelte';
	import Stats from './overview/Stats.svelte';
	import SendsChart from './overview/SendsChartView.svelte';
</script>

<SingleBox style="overflow-auto">
	<Stats />
	<SendsChart />
</SingleBox>
