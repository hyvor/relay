<script lang="ts">
	import { Modal, TextInput, Button } from '@hyvor/design/components';
	import { queuesStore, ipAddressesStore } from '../sudoStore';
	import { getQueues, updateIpAddress } from '../sudoActions';
	import type { Queue, IpAddress } from '../sudoTypes';
	import { toast } from '@hyvor/design/components';
	import QueueRow from './QueueRow.svelte';
	import { onMount } from 'svelte';

	interface Props {
		show: boolean;
		ip: IpAddress | null;
		onClose: () => void;
		onUpdate: (updatedIp: IpAddress) => void;
	}

	let { show = $bindable(), ip, onClose, onUpdate }: Props = $props();

	let searchQuery = $state('');

	let loading = $state(true);
	let updating = $state(false);

	const filteredQueues = $derived(
		$queuesStore.filter((queue) => queue.name.toLowerCase().includes(searchQuery.toLowerCase()))
	);

	function handleClose() {
		show = false;
		searchQuery = '';
		onClose();
	}

	async function updateQueue(
		queueId: number | null,
		successMessage: string,
		errorMessage: string
	) {
		if (!ip) return;

		updating = true;
		try {
			const updatedIp = await updateIpAddress(ip.id, { queue_id: queueId });

			ipAddressesStore.update((ips) =>
				ips.map((existingIp) => (existingIp.id === ip.id ? updatedIp : existingIp))
			);

			onUpdate(updatedIp);
			toast.success(successMessage);
			handleClose();
		} catch (error: any) {
			toast.error(errorMessage + error.message);
		} finally {
			updating = false;
		}
	}

	async function handleQueueSelect(queue: Queue) {
		await updateQueue(
			queue.id,
			`Queue "${queue.name}" assigned to IP ${ip?.ip_address}`,
			'Failed to assign queue: '
		);
	}

	async function handleQueueUnassign() {
		await updateQueue(
			null,
			`Queue unassigned from IP ${ip?.ip_address}`,
			'Failed to unassign queue: '
		);
	}

	onMount(() => {
		getQueues()
			.then((queuesResponse) => {
				queuesStore.set(queuesResponse);
			})
			.catch((err) => {
				toast.error('Failed to load queues: ' + err.message);
			})
			.finally(() => {
				loading = false;
			});
	});
</script>

<Modal
	bind:show
	size="medium"
	title={ip ? `${ip.queue ? 'Change' : 'Assign'} Queue for IP ${ip.ip_address}` : 'Select Queue'}
	footer={{
		cancel: {
			text: 'Cancel'
		},
		confirm: false
	}}
	on:cancel={handleClose}
	loading={updating || loading}
>
	<div class="modal-content">
		{#if ip?.queue}
			<div class="current-queue">
				<div class="current-queue-name">
					<span class="queue-tag">Current Queue:</span>
					{ip.queue.name}
				</div>
				<Button
					size="small"
					color="red"
					variant="outline"
					on:click={handleQueueUnassign}
					disabled={updating}
				>
					Unassign
				</Button>
			</div>
		{/if}

		<div class="search-section">
			<TextInput
				bind:value={searchQuery}
				placeholder="Search queues..."
				block
				disabled={updating}
			/>
		</div>

		<div class="available-queues">
			{#if filteredQueues.length === 0}
				<div class="no-results">
					{searchQuery ? 'No queues found matching your search.' : 'No queues available.'}
				</div>
			{:else}
				<div class="queue-list">
					{#each filteredQueues as queue}
						<button
							class="queue-item"
							class:disabled={updating || ip?.queue?.id === queue.id}
							onclick={() => handleQueueSelect(queue)}
						>
							<QueueRow {queue} />
						</button>
					{/each}
				</div>
			{/if}
		</div>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}

	.search-section {
		margin-bottom: 20px;
	}

	.current-queue {
		display: flex;
		align-items: center;
		margin-bottom: 20px;
		padding-bottom: 20px;
		border-bottom: 1px solid var(--border);
	}
	.current-queue-name {
		flex: 1;
		font-weight: 600;
	}
	.queue-tag {
		font-weight: normal;
		color: var(--text-light);
		font-size: 14px;
	}

	.queue-item {
		text-align: left;
		padding: 2px 0;
		border-radius: 8px;
		transition: background-color 0.2s;
		width: 100%;
	}

	.queue-item:hover:not(.disabled) {
		background-color: var(--hover);
	}

	.queue-item.disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}

	.queue-list {
		max-height: 300px;
		overflow-y: auto;
	}

	.no-results {
		text-align: center;
		color: var(--text-light);
		padding: 40px 20px;
		font-style: italic;
	}

	.available-queues {
		margin-top: 20px;
	}
</style>
