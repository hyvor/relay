<script lang="ts">
	import { page } from '$app/state';
	import { Button, Loader, Select, toast } from '@hyvor/design/components';
	import { onMount } from 'svelte';
	import { getIpAddresses, getWarmupSchedules } from '../../sudoActions';
	import { ipAddressesStore, warmupSchedulesStore } from '../../sudoStore';
	import WarmupScheduleRow from './WarmupScheduleRow.svelte';

	let loading = $state(true);

	let filterIpId = $state(page.url.searchParams.get('ip') ?? '');

	let ipFilterOptions = $derived([
		{ value: '', label: 'All IP Addresses' },
		...$ipAddressesStore.map((ip) => ({ value: String(ip.id), label: ip.ip_address }))
	]);

	let sortedSchedules = $derived(
		[...$warmupSchedulesStore].sort((a, b) => b.created_at - a.created_at)
	);

	function ipAddressFor(ipAddressId: number): string {
		return $ipAddressesStore.find((ip) => ip.id === ipAddressId)?.ip_address ?? `#${ipAddressId}`;
	}

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

	onMount(() => {
		getIpAddresses()
			.then((ips) => ipAddressesStore.set(ips))
			.catch((error: any) => {
				toast.error('Failed to load IP addresses: ' + error.message);
			});
	});

	$effect(() => {
		filterIpId;
		loadSchedules();
	});
</script>

<div class="ip-warmups">
	<div class="top">
		<div class="filters">
			<Select bind:value={filterIpId} options={ipFilterOptions} size="small" block={false} />
		</div>

		<Button as="a" href="/sudo/settings/ip-warmups/new">New Warmup</Button>
	</div>

	{#if loading}
		<Loader size="large" />
	{:else if sortedSchedules.length === 0}
		<div class="empty">No warmup schedules found.</div>
	{:else}
		<div class="rows">
			{#each sortedSchedules as schedule (schedule.id)}
				<WarmupScheduleRow {schedule} ipAddress={ipAddressFor(schedule.ip_address_id)} />
			{/each}
		</div>
	{/if}
</div>

<style>
	.ip-warmups {
		padding: 30px 40px;
		overflow: auto;
	}

	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 20px;
		flex-wrap: wrap;
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

	.empty {
		text-align: center;
		padding: 40px;
		color: var(--text-light);
	}
</style>
