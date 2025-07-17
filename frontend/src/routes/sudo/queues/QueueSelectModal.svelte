<script lang="ts">
	import { Modal, TextInput, Button } from '@hyvor/design/components';
	import { queuesStore, ipAddressesStore } from '../sudoStore';
	import { updateIpAddress } from '../sudoActions';
	import type { Queue, IpAddress } from '../sudoTypes';
	import { toast } from '@hyvor/design/components';
	import QueueRow from './QueueRow.svelte';

	interface Props {
		show: boolean;
		ip: IpAddress | null;
		onClose: () => void;
		onUpdate: (updatedIp: IpAddress) => void;
	}

	let { show = $bindable(), ip, onClose, onUpdate }: Props = $props();

	let searchQuery = $state('');
	let loading = $state(false);

	const filteredQueues = $derived(
		$queuesStore.filter(queue => 
			queue.name.toLowerCase().includes(searchQuery.toLowerCase())
		)
	);

	function handleClose() {
		show = false;
		searchQuery = '';
		onClose();
	}

	async function updateQueue(queueId: number | null, successMessage: string, errorMessage: string) {
		if (!ip) return;
		
		loading = true;
		try {
			const updatedIp = await updateIpAddress(ip.id, { queue_id: queueId });
			
			ipAddressesStore.update(ips => 
				ips.map(existingIp => 
					existingIp.id === ip.id ? updatedIp : existingIp
				)
			);
			
			onUpdate(updatedIp);
			toast.success(successMessage);
			handleClose();
		} catch (error: any) {
			toast.error(errorMessage + error.message);
		} finally {
			loading = false;
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
	{loading}
>
	<div class="modal-content">
		<div class="search-section">
			<TextInput
				bind:value={searchQuery}
				placeholder="Search queues..."
				block
				disabled={loading}
			/>
		</div>

		{#if ip?.queue}
			<div class="current-queue">
				<div class="section-title">Current Queue</div>
				<div class="queue-item current">
					<QueueRow queue={ip.queue} />
					<Button
						size="small"
						color="red"
						variant="outline"
						on:click={handleQueueUnassign}
						disabled={loading}
					>
						Unassign
					</Button>
				</div>
			</div>
		{/if}

		<div class="available-queues">
			<div class="section-title">
				{ip?.queue ? 'Available Queues' : 'Select a Queue'}
			</div>
			
			{#if filteredQueues.length === 0}
				<div class="no-results">
					{searchQuery ? 'No queues found matching your search.' : 'No queues available.'}
				</div>
			{:else}
				<div class="queue-list">
					{#each filteredQueues as queue}
						<div class="queue-item" class:disabled={loading}>
							<QueueRow {queue} />
							<Button
								size="small"
								color="input"
								on:click={() => handleQueueSelect(queue)}
								disabled={loading || (ip?.queue?.id === queue.id)}
							>
								{ip?.queue?.id === queue.id ? 'Current' : 'Select'}
							</Button>
						</div>
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
		margin-bottom: 20px;
		padding-bottom: 20px;
		border-bottom: 1px solid var(--border);
	}

	.section-title {
		font-weight: 600;
		margin-bottom: 15px;
		color: var(--text);
	}

	.queue-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 8px 0;
		border-radius: 8px;
		transition: background-color 0.2s;
	}

	.queue-item:hover:not(.disabled) {
		background-color: var(--background-light);
	}

	.queue-item.current {
		background-color: var(--green-50);
		padding: 12px 16px;
		border-radius: 8px;
		border: 1px solid var(--green-200);
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
