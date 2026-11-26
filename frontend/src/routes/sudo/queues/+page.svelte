<script lang="ts">
	import { onMount } from 'svelte';
	import { Button, Loader, toast } from '@hyvor/design/components';
	import { getQueues } from '../sudoActions';
	import { queuesStore } from '../sudoStore';
	import type { QueueType } from '../sudoTypes';
	import QueueRow from './QueueRow.svelte';
	import CreateQueueModal from './CreateQueueModal.svelte';
	import SingleBox from '../SingleBox.svelte';

	let loading = $state(true);
	let creating = $state(false);

	const groups: { type: QueueType; label: string }[] = [
		{ type: 'transactional', label: 'Transactional' },
		{ type: 'distributional', label: 'Distributional' },
		{ type: 'dedicated', label: 'Dedicated' }
	];

	onMount(() => {
		getQueues()
			.then((queuesResponse) => {
				queuesStore.set(queuesResponse);
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
				Each email is sent to a queue. A server with an IP address assigned to that queue
				will process the email. Each project is routed to a transactional or distributional
				queue based on its type.
			</div>
			<Button size="small" onclick={() => (creating = true)}>Create Queue</Button>
		</div>

		{#each groups as group}
			{@const queues = $queuesStore.filter((q) => q.type === group.type)}
			{#if queues.length > 0}
				<div class="group">
					<div class="group-title">{group.label}</div>
					{#each queues as queue (queue.id)}
						<QueueRow {queue} actions />
					{/each}
				</div>
			{/if}
		{/each}
	{/if}
</SingleBox>

{#if creating}
	<CreateQueueModal bind:show={creating} />
{/if}

<style>
	.group {
		padding: 15px 20px;
	}
	.group + .group {
		border-top: 1px solid var(--border);
	}
	.group-title {
		font-weight: 600;
		padding: 0 20px 5px;
	}
	.header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 20px;
		padding: 20px;
		border-bottom: 1px solid var(--border);
	}
	.tip {
		font-size: 14px;
		color: var(--text-light);
		flex: 1;
	}
</style>
