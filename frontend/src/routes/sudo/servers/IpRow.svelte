<script lang="ts">
	import { Button, Switch, Tag, Tooltip } from '@hyvor/design/components';
	import type { IpAddress } from '../sudoTypes';
	import IconExclamationCircle from '@hyvor/icons/IconExclamationCircle';
	import QueueSelectModal from '../queues/QueueSelectModal.svelte';

	interface Props {
		ip: IpAddress;
	}

	let { ip = $bindable() }: Props = $props();

	let showQueueModal = $state(false);

	function handleQueueButtonClick() {
		showQueueModal = true;
	}

	function handleModalClose() {
		showQueueModal = false;
	}

	function handleIpUpdate(updatedIp: IpAddress) {
		ip = updatedIp;
	}
</script>

<tr>
	<td class="id">
		{ip.id}
	</td>
	<td class="ip-address">
		{ip.ip_address}
	</td>
	<td class="queue-name">
		{#if ip.queue}
			{ip.queue.name}
		{:else}
			<Tooltip
				text="This IP address will not be used for email delivery until you assign a queue to it."
			>
				<span class="none">
					None
					<IconExclamationCircle size={14} />
				</span>
			</Tooltip>
		{/if}

		<Button size="x-small" color="input" style="margin-left: 5px;" on:click={handleQueueButtonClick}>
			{ip.queue ? 'Change' : 'Assign'}
		</Button>
	</td>
	<td>
		<div class="ptr">{ip.ptr}</div>
		<div class="ptr-tags">
			<Tag size="small" color="green">Forward ok</Tag>
			<Tag size="small" color="green">Reverse ok</Tag>
		</div>
	</td>
	<td>
		<Switch />
	</td>
	<td class="">
		<Tag size="small" color="green">Enabled</Tag>
	</td>
</tr>

<QueueSelectModal
	bind:show={showQueueModal}
	{ip}
	onClose={handleModalClose}
	onUpdate={handleIpUpdate}
/>

<style>
	.id {
		color: var(--text-light);
		font-size: 14px;
	}
	td {
		padding: 0.75rem;
		text-align: left;
	}
	.none {
		color: var(--orange-dark);
		font-size: 14px;
		display: inline-flex;
		align-items: center;
		gap: 5px;
	}

	.ptr-tags {
		margin-top: 5px;
	}
</style>
