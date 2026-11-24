<script lang="ts">
	import { confirm, IconButton, Tag, toast } from '@hyvor/design/components';
	import type { Queue } from '../sudoTypes';
	import IconExclamationCircle from '@hyvor/icons/IconExclamationCircle';
	import IconTrash from '@hyvor/icons/IconTrash';
	import { ipAddressesStore, queuesStore } from '../sudoStore';
	import { deleteQueue } from '../sudoActions';
	import DeleteQueueConfirm from './DeleteQueueConfirm.svelte';

	interface Props {
		queue: Queue;
		// false when rendered inside the queue select modal
		actions?: boolean;
	}

	let { queue, actions = false }: Props = $props();

	const ipCount = $derived($ipAddressesStore.filter((ip) => ip.queue?.id === queue.id).length);
	const canDelete = $derived(!queue.is_reserved && ipCount === 0);

	async function handleDelete() {
		const confirmed = await confirm({
			title: 'Delete Queue',
			content: DeleteQueueConfirm,
			contentProps: { name: queue.full_name },
			confirmText: 'Delete',
			cancelText: 'Cancel',
			danger: true,
			autoClose: false
		});

		if (!confirmed) return;

		confirmed.loading();

		deleteQueue(queue.id)
			.then(() => {
				toast.success('Queue deleted successfully');
				queuesStore.update((queues) => queues.filter((q) => q.id !== queue.id));
			})
			.catch((error) => {
				toast.error('Failed to delete queue: ' + error.message);
			})
			.finally(() => {
				confirmed.close();
			});
	}
</script>

<div class="queue">
	<div class="id">
		({queue.id})
	</div>
	<div class="name">
		{queue.name}
		{#if queue.is_reserved}
			<Tag size="x-small">Reserved</Tag>
		{/if}
	</div>
	{#if actions && queue.projects_count !== null}
		<div class="projects-count">
			{queue.projects_count} project{queue.projects_count !== 1 ? 's' : ''}
		</div>
	{/if}
	<div class="ip-count">
		<Tag size="small" color={ipCount > 0 ? 'green' : 'orange'}>
			{ipCount} IP{ipCount !== 1 ? 's' : ''}

			{#snippet end()}
				{#if ipCount === 0}
					<IconExclamationCircle size={12} />
				{/if}
			{/snippet}
		</Tag>
	</div>
	{#if actions}
		<div class="actions">
			{#if canDelete}
				<IconButton color="red" variant="fill-light" size="small" on:click={handleDelete}>
					<IconTrash size={12} />
				</IconButton>
			{/if}
		</div>
	{/if}
</div>

<style>
	.queue {
		display: flex;
		align-items: center;
		padding: 8px 20px;
		gap: 10px;
	}
	.id {
		color: var(--text-light);
		font-size: 14px;
		flex-shrink: 0;
	}
	.name {
		flex: 1;
		display: flex;
		align-items: center;
		gap: 6px;
	}
	.projects-count {
		color: var(--text-light);
		font-size: 14px;
		flex-shrink: 0;
	}
	.ip-count {
		flex-shrink: 0;
	}
	.actions {
		flex-shrink: 0;
		width: 30px;
		display: flex;
		justify-content: flex-end;
	}
</style>
