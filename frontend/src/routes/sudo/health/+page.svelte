<script lang="ts">
	import { onMount } from 'svelte';
	import { Button, Loader, toast } from '@hyvor/design/components';
	import { getHealthChecks, runHealthChecks } from '../sudoActions';
	import type { HealthCheckResults } from '../sudoTypes';
	import SingleBox from '../SingleBox.svelte';
	import HealthCheckItem from './HealthCheckItem.svelte';
	import dayjs from 'dayjs';
	import relativeTime from 'dayjs/plugin/relativeTime';
	import RelativeTime from '../../console/@components/content/RelativeTime.svelte';

	dayjs.extend(relativeTime);

	let healthCheckResults: HealthCheckResults | null = $state(null);
	let loading = $state(true);
	let running = $state(false);

	function loadHealthChecks() {
		loading = true;
		getHealthChecks()
			.then((results) => {
				healthCheckResults = results;
			})
			.catch((error: any) => {
				toast.error('Failed to load health checks: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleRunHealthChecks() {
		running = true;
		runHealthChecks()
			.then((results) => {
				healthCheckResults = results;
				toast.success('Health checks completed successfully');
			})
			.catch((error: any) => {
				toast.error('Failed to run health checks: ' + error.message);
			})
			.finally(() => {
				running = false;
			});
	}

	onMount(() => {
		loadHealthChecks();
	});
</script>

<SingleBox>
	{#if loading}
		<Loader full />
	{:else if healthCheckResults}
		<div class="health-checks">
			<div class="header">
				<div class="header-content">
					<div class="title">Health Checks</div>
					<div class="last-checked">
						Last checked: <RelativeTime unix={healthCheckResults.last_checked_at!} />
					</div>
				</div>
				<Button
					color="accent"
					size="small"
					onclick={handleRunHealthChecks}
					disabled={running}
				>
					{running ? 'Running...' : 'Run Checks'}
				</Button>
			</div>

			<div class="checks">
				{#each Object.entries(healthCheckResults.results) as [checkKey, result]}
					<HealthCheckItem checkKey={checkKey as any} {result} />
				{/each}
			</div>
		</div>
	{:else}
		<div class="error">
			<div class="title">Health Checks</div>
			<div class="error-message">Failed to load health check results</div>
			<Button color="accent" size="small" onclick={loadHealthChecks}>Retry</Button>
		</div>
	{/if}
</SingleBox>

<style>
	.health-checks {
		padding: 30px;
		border-bottom: 1px solid var(--border);
	}

	.header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 20px;
	}

	.header-content {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.title {
		font-size: 18px;
		font-weight: bold;
	}

	.last-checked {
		font-size: 14px;
		color: var(--text-light);
	}

	.checks {
		display: flex;
		flex-direction: column;
	}

	.error {
		padding: 30px;
		text-align: center;
	}

	.error-message {
		font-size: 14px;
		color: var(--text-light);
		margin: 10px 0;
	}
</style>
