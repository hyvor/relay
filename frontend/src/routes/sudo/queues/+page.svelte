<script lang="ts">
	import { onMount } from 'svelte';
	import { Loader, toast } from '@hyvor/design/components';
	import { getIpAddresses, getQueues } from '../sudoActions';
	import { ipAddressesStore, queuesStore } from '../sudoStore';
	import QueueRow from './QueueRow.svelte';
	import SingleBox from '../SingleBox.svelte';

	let loading = $state(true);

	onMount(async () => {
		Promise.all([await getQueues(), await getIpAddresses()])
			.then(([queuesResponse, ipsResponse]) => {
				queuesStore.set(queuesResponse);
				ipAddressesStore.set(ipsResponse);
			})
			.catch((err) => {
				toast.error('Failed to load data: ' + err.message);
			})
			.finally(() => {
				loading = false;
			});
	});
</script>

<SingleBox>
	{#if loading}
		<Loader full />
	{:else}
		<div class="header">
			<div class="tip">
				Each email is sent to a queue. A server with an IP address asssigned to that queue will
				process the email. By default, emails are sent to transactional or distributional queues
				based on the project type. For users with dedicated IPs, a dedicated queue is used.
			</div>
		</div>

		<div class="server-list">
			{#each $queuesStore as queue}
				<QueueRow {queue} />
			{/each}
		</div>
	{/if}
</SingleBox>

<style>
	.server-list {
		padding: 20px;
	}
	.header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px;
		border-bottom: 1px solid var(--border);
	}
	.tip {
		font-size: 14px;
		color: var(--text-light);
		flex: 1;
	}
</style>
