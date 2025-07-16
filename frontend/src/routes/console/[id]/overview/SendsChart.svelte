<script lang="ts">
	import { onMount } from 'svelte';
	import type { AnalyticsSendChartRow } from '../../lib/actions/analyticsActions';
	import Chart from 'chart.js/auto';

	interface Props {
		data?: AnalyticsSendChartRow[];
	}

	let { data = [] }: Props = $props();
	let canvas: HTMLCanvasElement = $state({} as HTMLCanvasElement);

	function createChart() {
		const labels = data.map((row) =>
			new Date(row.date).toLocaleDateString('en-US', {
				month: 'short',
				day: 'numeric'
			})
		);

		const datasets = [
			{
				label: 'Queued',
				data: data.map((row) => row.queued),
				backgroundColor: '#777' // grey
			},
			{
				label: 'Accepted',
				data: data.map((row) => row.accepted),
				backgroundColor: '#cadfca' // var(--green-light )
			},
			{
				label: 'Bounced',
				data: data.map((row) => row.bounced),
				backgroundColor: '#ffdfdf' // var(--red-light)
			},
			{
				label: 'Complained',
				data: data.map((row) => row.complained),
				backgroundColor: '#efe3b4' // var(--orange-light)
			}
		];

		return new Chart(canvas, {
			type: 'bar',
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					intersect: false,
					mode: 'index'
				},
				plugins: {
					legend: {
						display: false
					}
				},
				scales: {
					x: {
						stacked: true,
						ticks: {
							autoSkip: true,
							maxTicksLimit: 10
						},
						grid: {
							display: false
						}
					},
					y: {
						stacked: true,
						border: {
							display: false
						},
						ticks: {
							precision: 0
						}
					}
				},
				elements: {
					point: {
						radius: 0
					},
					line: {
						tension: 0.4
					}
				}
			},
			data: {
				labels,
				datasets: datasets
			}
		});
	}

	onMount(createChart);
</script>

<canvas bind:this={canvas}></canvas>

<style>
	canvas {
		width: 100% !important;
		height: 100%;
	}
</style>
