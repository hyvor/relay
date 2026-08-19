<script lang="ts">
	import { Modal, Button, confirm, toast } from '@hyvor/design/components';
	import { updateWarmupSchedule } from '../sudoActions';
	import { ipAddressesStore } from '../sudoStore';
	import WarmupScheduleProgress from './WarmupScheduleProgress.svelte';
	import type { IpAddress } from '../sudoTypes';

	interface Props {
		show: boolean;
		ip: IpAddress | null;
		onClose: () => void;
		onUpdate: (updatedIp: IpAddress) => void;
	}

	let { show = $bindable(), ip, onClose, onUpdate }: Props = $props();

	let warmup = $derived(ip?.currentWarmupSchedule ?? null);

	function handleClose() {
		show = false;
		onClose();
	}

	async function handleCancelWarmup() {
		if (!ip || !warmup) return;

		const confirmed = await confirm({
			title: 'Cancel Warmup Schedule',
			content: `Are you sure you want to cancel the warmup schedule for IP ${ip.ip_address}? The IP will no longer be rate limited.`,
			confirmText: 'Cancel Warmup',
			cancelText: 'Keep Warming',
			danger: true,
			autoClose: false
		});

		if (!confirmed) return;

		confirmed.loading();

		try {
			const updatedWarmup = await updateWarmupSchedule(warmup.id, {
				status: 'cancelled'
			});

			const updatedIp: IpAddress = {
				...ip,
				currentWarmupSchedule: updatedWarmup
			};

			ipAddressesStore.update((ips) =>
				ips.map((existingIp) => (existingIp.id === ip.id ? updatedIp : existingIp))
			);

			onUpdate(updatedIp);
			toast.success(`Warmup cancelled for IP ${ip.ip_address}`);
			handleClose();
		} catch (error: any) {
			toast.error('Failed to cancel warmup: ' + error.message);
		} finally {
			confirmed.close();
		}
	}
</script>

<Modal
	bind:show
	size="large"
	title={ip ? `Warmup Schedule for IP ${ip.ip_address}` : 'Warmup Schedule'}
	footer={{
		cancel: { text: 'Close' },
		confirm: false
	}}
	on:cancel={handleClose}
>
	{#if warmup}
		<div class="modal-content">
			<div class="top-actions">
				<Button size="small" color="red" variant="outline" on:click={handleCancelWarmup}>
					Cancel Warmup Schedule
				</Button>
			</div>

			<WarmupScheduleProgress schedule={warmup} />
		</div>
	{/if}
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
		max-height: 70vh;
		overflow-y: auto;
	}

	.top-actions {
		display: flex;
		justify-content: flex-end;
		margin-bottom: 20px;
	}
</style>
