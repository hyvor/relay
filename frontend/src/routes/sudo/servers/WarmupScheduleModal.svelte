<script lang="ts">
	import { Modal, Button, TextInput } from '@hyvor/design/components';
	import { updateIpAddress } from '../sudoActions';
	import { ipAddressesStore } from '../sudoStore';
	import { toast } from '@hyvor/design/components';
	import type { IpAddress } from '../sudoTypes';

	interface Props {
		show: boolean;
		ip: IpAddress | null;
		onClose: () => void;
		onUpdate: (updatedIp: IpAddress) => void;
	}

	let { show = $bindable(), ip, onClose, onUpdate }: Props = $props();

	const DEFAULT_SCHEDULE = [
		50, 100, 250, 500, 1000,
		2500, 5000, 10000, 10000, 20000, 20000,
		40000, 40000, 75000, 150000, 150000, 150000,
		150000, 150000, 300000, 300000, 300000, 300000,
		300000, 300000, 500000, 500000, 500000, 1000000, 1000000
	];

	let schedule = $state<number[]>([]);
	let loading = $state(false);

	function initSchedule() {
		if (ip?.warmup_schedule && ip.warmup_schedule.length === 30) {
			schedule = [...ip.warmup_schedule];
		} else {
			schedule = Array(30).fill(0);
		}
	}

	$effect(() => {
		if (show) {
			initSchedule();
		}
	});

	function handleClose() {
		show = false;
		onClose();
	}

	function handleInputChange(index: number, value: string) {
		const numValue = parseInt(value, 10);
		if (isNaN(numValue) || numValue < 0) return;

		const newSchedule = [...schedule];
		newSchedule[index] = numValue;

		// Auto-fill all subsequent days to be at least this value
		for (let i = index + 1; i < 30; i++) {
			if (newSchedule[i] < numValue) {
				newSchedule[i] = numValue;
			}
		}

		schedule = newSchedule;
	}

	function applyDefaultSchedule() {
		schedule = [...DEFAULT_SCHEDULE];
	}

	async function handleSave() {
		if (!ip) return;

		for (let i = 1; i < 30; i++) {
			if (schedule[i] < schedule[i - 1]) {
				toast.error(`Day ${i + 1} value cannot be less than Day ${i} value.`);
				return;
			}
		}

		loading = true;
		try {
			const updatedIp = await updateIpAddress(ip.id, {
				warmup_schedule: schedule,
				warmup_status: 'warming'
			});

			ipAddressesStore.update((ips) =>
				ips.map((existingIp) => (existingIp.id === ip.id ? updatedIp : existingIp))
			);

			onUpdate(updatedIp);
			toast.success(`Warmup schedule started for IP ${ip.ip_address}`);
			handleClose();
		} catch (error: any) {
			toast.error('Failed to start warmup: ' + error.message);
		} finally {
			loading = false;
		}
	}
</script>

<Modal
	bind:show
	size="large"
	title={ip ? `Warmup Schedule for IP ${ip.ip_address}` : 'Warmup Schedule'}
	footer={{
		cancel: { text: 'Cancel' },
		confirm: { text: 'Start Warmup' }
	}}
	on:cancel={handleClose}
	on:confirm={handleSave}
	loading={loading}
>
	<div class="modal-content">
		<div class="actions">
			<Button size="small" color="input" variant="outline" on:click={applyDefaultSchedule} disabled={loading}>
				Use Default Schedule
			</Button>
		</div>

		<div class="schedule-grid">
			{#each schedule as value, i (i)}
				<div class="day-input">
					<label class="day-label" for="day-{i}">Day {i + 1}</label>
					<TextInput
						id="day-{i}"
						type="number"
						value={value}
						on:input={(e) => handleInputChange(i, e.currentTarget.value)}
						block
						disabled={loading}
						min="0"
					/>
				</div>
			{/each}
		</div>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
		max-height: 70vh;
		overflow-y: auto;
	}

	.actions {
		margin-bottom: 20px;
	}

	.schedule-grid {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		gap: 12px;
	}

	.day-input {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.day-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: 500;
	}

	@media (max-width: 900px) {
		.schedule-grid {
			grid-template-columns: repeat(3, 1fr);
		}
	}

	@media (max-width: 600px) {
		.schedule-grid {
			grid-template-columns: repeat(2, 1fr);
		}
	}
</style>
