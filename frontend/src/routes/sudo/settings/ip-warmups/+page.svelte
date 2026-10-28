<script lang="ts">
	import { page } from '$app/state';
	import { get } from 'svelte/store';
	import { Button, IconMessage, Loader, toast } from '@hyvor/design/components';
	import { getWarmupSchedules } from '../../sudoActions';
	import { ipAddressesStore, warmupSchedulesStore } from '../../sudoStore';
	import type { IpAddress } from '../../sudoTypes';
	import WarmupScheduleRow from './WarmupScheduleRow.svelte';
	import IpAddressSelector from './IpAddressSelector.svelte';

	let loading = $state(true);

	const ipParam = page.url.searchParams.get('ip');

	let selectedIp: IpAddress | null = $state(
		ipParam ? (get(ipAddressesStore).find((ip) => ip.ip_address === ipParam) ?? null) : null
	);

	let sortedSchedules = $derived(
		[...$warmupSchedulesStore].sort((a, b) => b.created_at - a.created_at)
	);

	let autoOpenScheduleId = $derived(
		ipParam
			? (sortedSchedules.find((s) => s.ip_address === ipParam && s.status === 'warming')
					?.id ?? null)
			: null
	);

	function loadSchedules() {
		loading = true;
		return getWarmupSchedules(selectedIp?.id)
			.then((schedules) => {
				warmupSchedulesStore.set(schedules);
			})
			.catch((error: any) => {
				toast.error('Failed to load warmup schedules: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	$effect(() => {
		selectedIp;
		loadSchedules();
	});
</script>

<div class="ip-warmups">
	<div class="top">
		<div class="filters">
			<IpAddressSelector bind:selectedIp />
		</div>

		<Button as="a" href="/sudo/settings/ip-warmups/new">
			New Warmup
			{#snippet end()}
				&plus;
			{/snippet}
		</Button>
	</div>

	<div class="content">
		{#if loading}
			<Loader size="large" />
		{:else if sortedSchedules.length === 0}
			<IconMessage empty>No warmup schedules found.</IconMessage>
		{:else}
			<div class="rows">
				{#each sortedSchedules as schedule (schedule.id)}
					<WarmupScheduleRow
						{schedule}
						initiallyOpen={schedule.id === autoOpenScheduleId}
					/>
				{/each}
			</div>
		{/if}
	</div>
</div>

<style>
	.ip-warmups {
		overflow: auto;
	}

	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		flex-wrap: wrap;
		padding: 15px 40px;
		border-bottom: 1px solid var(--border);
	}

	.filters {
		display: flex;
		gap: 10px;
		align-items: center;
	}

	.rows {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.content {
		padding: 20px 40px;
	}
</style>
