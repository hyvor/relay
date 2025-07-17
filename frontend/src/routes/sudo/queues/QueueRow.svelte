<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import IconExclamationTriangle from '@hyvor/icons/IconExclamationTriangle';
	import type { Queue } from '../sudoTypes';
	import { ipAddressesStore } from '../sudoStore';

	interface Props {
		queue: Queue;
	}

	let { queue }: Props = $props();

	const ipCount = $derived(
		$ipAddressesStore.filter(ip => ip.queue?.id === queue.id).length
	);
</script>

<div class="queue">
	<div class="id">
		({queue.id})
	</div>
	<div class="name">
		{queue.name}
	</div>
	<div class="ip-count">
		<Tag size="small" color={ipCount > 0 ? 'green' : 'default'}>
			{ipCount} IP{ipCount !== 1 ? 's' : ''}
			{#if ipCount === 0}
				<IconExclamationTriangle size={12} />
			{/if}
		</Tag>
	</div>
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
	}
	.ip-count {
		flex-shrink: 0;
	}
</style>
