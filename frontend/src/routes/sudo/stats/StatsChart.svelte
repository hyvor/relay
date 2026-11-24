<script lang="ts">
	import { onMount } from 'svelte';
	import Chart from 'chart.js/auto';
	import type { SudoStatsDaily } from '../sudoTypes';

	interface Props {
		data: SudoStatsDaily[];
		mode: 'volume' | 'rates';
	}

	let { data, mode }: Props = $props();
	let canvas: HTMLCanvasElement;
	let chart: Chart | null = null;

	const GREEN = '#7fb77f';
	const GREY = '#b5b5b5';
	const ORANGE = '#e0a23b';
	const RED = '#e06666';
	const PURPLE = '#9b6bd1';

	function labels() {
		return data.map((row) =>
			new Date(row.date + 'T00:00:00').toLocaleDateString('en-US', {
				month: 'short',
				day: 'numeric'
			})
		);
	}

	function build() {
		if (mode === 'volume') {
			return {
				type: 'bar' as const,
				datasets: [
					{ label: 'Accepted', data: data.map((d) => d.accepted), backgroundColor: GREEN },
					{ label: 'Deferred', data: data.map((d) => d.deferred), backgroundColor: GREY },
					{ label: 'Bounced', data: data.map((d) => d.bounced), backgroundColor: RED },
					{ label: 'Failed', data: data.map((d) => d.failed), backgroundColor: PURPLE }
				]
			};
		}
		return {
			type: 'line' as const,
			datasets: [
				{
					label: 'Bounce %',
					data: data.map((d) => d.rates.bounce * 100),
					borderColor: RED,
					backgroundColor: RED,
					yAxisID: 'y'
				},
				{
					label: 'Deferred %',
					data: data.map((d) => d.rates.deferred * 100),
					borderColor: GREY,
					backgroundColor: GREY,
					yAxisID: 'y'
				},
				{
					label: 'Complaint %',
					data: data.map((d) => d.rates.complaint * 100),
					borderColor: ORANGE,
					backgroundColor: ORANGE,
					yAxisID: 'y1'
				}
			]
		};
	}

	function render() {
		chart?.destroy();
		const { type, datasets } = build();
		const stacked = mode === 'volume';

		chart = new Chart(canvas, {
			type,
			data: { labels: labels(), datasets: datasets as any },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { intersect: false, mode: 'index' },
				plugins: { legend: { display: true, position: 'bottom' } },
				elements: { point: { radius: 0 }, line: { tension: 0.3, borderWidth: 2 } },
				scales: {
					x: {
						stacked,
						grid: { display: false },
						ticks: { autoSkip: true, maxTicksLimit: 10 }
					},
					y: {
						stacked,
						beginAtZero: true,
						border: { display: false },
						ticks: {
							precision: 0,
							callback: (v) => (stacked ? v : v + '%')
						}
					},
					...(stacked
						? {}
						: {
								y1: {
									position: 'right' as const,
									beginAtZero: true,
									grid: { display: false },
									border: { display: false },
									title: { display: true, text: 'Complaint %' },
									ticks: { callback: (v: any) => v + '%' }
								}
							})
				}
			}
		});
	}

	onMount(() => {
		render();
		return () => chart?.destroy();
	});

	$effect(() => {
		// re-render when data or mode changes
		data;
		mode;
		if (chart) render();
	});
</script>

<div class="chart"><canvas bind:this={canvas}></canvas></div>

<style>
	.chart {
		height: 260px;
		position: relative;
	}
</style>
