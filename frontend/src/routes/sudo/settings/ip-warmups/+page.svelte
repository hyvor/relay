<script lang="ts">
	import { page } from '$app/state';
	import { Button, IconMessage, Loader, toast } from '@hyvor/design/components';
	import { getWarmupSchedules } from '../../sudoActions';
	import { warmupSchedulesStore } from '../../sudoStore';
	import type { IpAddress } from '../../sudoTypes';
	import WarmupScheduleRow from './WarmupScheduleRow.svelte';
	import IpAddressSelector from './IpAddressSelector.svelte';

	let loading = $state(true);

	let filterIpId = $state(page.url.searchParams.get('ip') ?? '');
	let selectedIp: IpAddress | null = $state(null);
	let mounted = false;

	let sortedSchedules = $derived(
		[...$warmupSchedulesStore].sort((a, b) => b.created_at - a.created_at)
	);

	function loadSchedules() {
		const ipAddressId = filterIpId ? Number(filterIpId) : undefined;

		loading = true;
		return getWarmupSchedules(ipAddressId)
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
		if (mounted) {
			filterIpId = selectedIp ? String(selectedIp.id) : '';
		}
		mounted = true;
	});

	$effect(() => {
		filterIpId;
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
					<WarmupScheduleRow {schedule} />
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
