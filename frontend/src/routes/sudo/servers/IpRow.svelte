<script lang="ts">
	import { Button, Tooltip } from '@hyvor/design/components';
	import type { IpAddress } from '../sudoTypes';
	import IconExclamationCircle from '@hyvor/icons/IconExclamationCircle';
	import QueueSelectModal from '../queues/QueueSelectModal.svelte';
	import WarmupScheduleModal from './WarmupScheduleModal.svelte';
	import WarmupScheduleManageModal from './WarmupScheduleManageModal.svelte';
	import WarmupScheduleHistoryModal from './WarmupScheduleHistoryModal.svelte';
	import IpPtrStatus from './IpPtrStatus.svelte';

	interface Props {
		ip: IpAddress;
	}

	let { ip = $bindable() }: Props = $props();

	let showQueueModal = $state(false);
	let showWarmupModal = $state(false);
	let showManageModal = $state(false);
	let showHistoryModal = $state(false);

	const TOTAL_DAYS = 30;

	let warmup = $derived(ip.currentWarmupSchedule);
	let isWarming = $derived(warmup?.status === 'warming');

	let currentDay = $derived(warmup ? Math.min(warmup.results.length + 1, TOTAL_DAYS) : 0);
	let progressPercentage = $derived(Math.round((currentDay / TOTAL_DAYS) * 100));

	function handleQueueButtonClick() {
		showQueueModal = true;
	}

	function handleModalClose() {
		showQueueModal = false;
	}

	function handleWarmupModalClose() {
		showWarmupModal = false;
	}

	function handleManageModalClose() {
		showManageModal = false;
	}

	function handleHistoryModalClose() {
		showHistoryModal = false;
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
		{#if isWarming && warmup}
			<div class="warmup-day-progress">
				<div class="warmup-day-label">
					<span>Day {currentDay} of {TOTAL_DAYS}</span>
					<span>{progressPercentage}%</span>
				</div>
				<div class="progress-track">
					<div class="progress-fill" style="width: {progressPercentage}%"></div>
				</div>
			</div>
			<div class="warmup-actions">
				<Button
					size="x-small"
					color="input"
					variant="outline"
					on:click={() => (showManageModal = true)}
				>
					Manage
				</Button>
				<Button
					size="x-small"
					color="input"
					variant="outline"
					on:click={() => (showHistoryModal = true)}
				>
					History
				</Button>
			</div>
		{:else}
			<div class="warmup-actions">
				<Button
					size="x-small"
					color="input"
					variant="outline"
					on:click={() => (showWarmupModal = true)}
				>
					Start Warmup
				</Button>
				<Button
					size="x-small"
					color="input"
					variant="outline"
					on:click={() => (showHistoryModal = true)}
				>
					History
				</Button>
			</div>
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

{#if showManageModal}
	<WarmupScheduleManageModal
		bind:show={showManageModal}
		{ip}
		onClose={handleManageModalClose}
		onUpdate={handleIpUpdate}
	/>
{/if}

{#if showHistoryModal}
	<WarmupScheduleHistoryModal
		bind:show={showHistoryModal}
		{ip}
		onClose={handleHistoryModalClose}
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

	.warmup-actions {
		display: flex;
		gap: 4px;
	}

	.warmup-day-progress {
		margin-bottom: 8px;
		min-width: 160px;
	}

	.warmup-day-label {
		display: flex;
		justify-content: space-between;
		font-size: 12px;
		color: var(--text-light);
		margin-bottom: 4px;
	}

	.progress-track {
		height: 6px;
		background: var(--bg-input);
		border-radius: 3px;
		overflow: hidden;
	}

	.progress-fill {
		height: 100%;
		background: var(--orange);
		border-radius: 3px;
		transition: width 0.2s ease;
	}
</style>
