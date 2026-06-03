<script lang="ts">
	import { Button, Tag, Tooltip } from '@hyvor/design/components';
	import type { IpAddress } from '../sudoTypes';
	import IconExclamationCircle from '@hyvor/icons/IconExclamationCircle';
	import QueueSelectModal from '../queues/QueueSelectModal.svelte';
	import WarmupScheduleModal from './WarmupScheduleModal.svelte';
	import IpPtrStatus from './IpPtrStatus.svelte';
	import { updateIpAddress } from '../sudoActions';
	import { ipAddressesStore } from '../sudoStore';
	import { toast } from '@hyvor/design/components';

	interface Props {
		ip: IpAddress;
	}

	let { ip = $bindable() }: Props = $props();

	let showQueueModal = $state(false);
	let showWarmupModal = $state(false);

	function handleQueueButtonClick() {
		showQueueModal = true;
	}

	function handleModalClose() {
		showQueueModal = false;
	}

	function handleWarmupModalClose() {
		showWarmupModal = false;
	}

	function handleIpUpdate(updatedIp: IpAddress) {
		ip = updatedIp;
	}

	async function handleCancelWarmup() {
		try {
			const updatedIp = await updateIpAddress(ip.id, {
				warmup_status: 'warmed'
			});

			ipAddressesStore.update((ips) =>
				ips.map((existingIp) => (existingIp.id === ip.id ? updatedIp : existingIp))
			);

			ip = updatedIp;
			toast.success(`Warmup cancelled for IP ${ip.ip_address}`);
		} catch (error: any) {
			toast.error('Failed to cancel warmup: ' + error.message);
		}
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

		<Button
			size="x-small"
			color="input"
			style="margin-left: 5px;"
			on:click={handleQueueButtonClick}
		>
			{ip.queue ? 'Change' : 'Assign'}
		</Button>
	</td>
	<td>
		<div class="ptr">{ip.ptr}</div>
		<div class="ptr-tags">
			{#if ip.queue}
				<IpPtrStatus status={ip.is_ptr_forward_valid} forward />
				<IpPtrStatus status={ip.is_ptr_reverse_valid} />
			{/if}
		</div>
	</td>
	<td class="warmup">
		{#if ip.is_warming_up}
			<div class="warmup-info">
				<Tag color="orange" size="small">Warming</Tag>
				<span class="warmup-progress">
					{ip.warmup_sent_today.toLocaleString()} / {ip.warmup_max_today.toLocaleString()}
				</span>
			</div>
			<Button
				size="x-small"
				color="red"
				variant="outline"
				on:click={handleCancelWarmup}
			>
				Cancel
			</Button>
		{:else}
			<Tag color="green" size="small">Warmed</Tag>
			<Button
				size="x-small"
				color="input"
				variant="outline"
				on:click={() => (showWarmupModal = true)}
				style="margin-left: 5px;"
			>
				Create Schedule
			</Button>
		{/if}
	</td>
</tr>

{#if showQueueModal}
	<QueueSelectModal
		bind:show={showQueueModal}
		{ip}
		onClose={handleModalClose}
		onUpdate={handleIpUpdate}
	/>
{/if}

{#if showWarmupModal}
	<WarmupScheduleModal
		bind:show={showWarmupModal}
		{ip}
		onClose={handleWarmupModalClose}
		onUpdate={handleIpUpdate}
	/>
{/if}

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

	.warmup {
		white-space: nowrap;
	}

	.warmup-info {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 5px;
	}

	.warmup-progress {
		font-size: 13px;
		color: var(--text-light);
	}
</style>
