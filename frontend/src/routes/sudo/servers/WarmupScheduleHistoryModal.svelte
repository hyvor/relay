<script lang="ts">
	import { Modal, Loader, Tag, toast } from '@hyvor/design/components';
	import IconChevronRight from '@hyvor/icons/IconChevronRight';
	import { getWarmupSchedules } from '../sudoActions';
	import WarmupScheduleProgress from './WarmupScheduleProgress.svelte';
	import type { IpAddress, WarmupSchedule, WarmupStatus } from '../sudoTypes';

	interface Props {
		show: boolean;
		ip: IpAddress | null;
		onClose: () => void;
	}

	let { show = $bindable(), ip, onClose }: Props = $props();

	let schedules = $state<WarmupSchedule[]>([]);
	let loading = $state(false);
	let expanded = $state<number | null>(null);

	const TOTAL_DAYS = 30;

	$effect(() => {
		if (show && ip) {
			loadSchedules();
		}
	});

	async function loadSchedules() {
		if (!ip) return;

		loading = true;
		try {
			schedules = await getWarmupSchedules(ip.id);
		} catch (error: any) {
			toast.error('Failed to load warmup schedules: ' + error.message);
			schedules = [];
		} finally {
			loading = false;
		}
	}

	function handleClose() {
		show = false;
		onClose();
	}

	function toggle(id: number) {
		expanded = expanded === id ? null : id;
	}

	function formatDate(timestamp: number): string {
		return new Date(timestamp * 1000).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}

	function endDate(schedule: WarmupSchedule): string {
		const end = new Date(schedule.started_date * 1000);
		end.setDate(end.getDate() + TOTAL_DAYS);
		return formatDate(Math.floor(end.getTime() / 1000));
	}

	const statusColors: Record<WarmupStatus, 'orange' | 'green' | 'red'> = {
		warming: 'orange',
		warmed: 'green',
		cancelled: 'red'
	};
</script>

<Modal
	bind:show
	size="large"
	title={ip ? `Warmup History for IP ${ip.ip_address}` : 'Warmup History'}
	footer={{
		cancel: { text: 'Close' },
		confirm: false
	}}
	on:cancel={handleClose}
	{loading}
>
	<div class="modal-content">
		{#if loading}
			<Loader block size="large" padding="large" />
		{:else if schedules.length === 0}
			<div class="empty">No warmup schedules found for this IP.</div>
		{:else}
			<div class="rows">
				{#each schedules as schedule (schedule.id)}
					<div class="row" class:open={expanded === schedule.id}>
						<button class="row-header" onclick={() => toggle(schedule.id)}>
							<span class="chevron" class:rotated={expanded === schedule.id}>
								<IconChevronRight size={14} />
							</span>
							<span class="row-dates">
								{formatDate(schedule.started_date)} &ndash; {endDate(schedule)}
							</span>
							<Tag color={statusColors[schedule.status]} size="small">
								{schedule.status}
							</Tag>
						</button>

						{#if expanded === schedule.id}
							<div class="row-body">
								<WarmupScheduleProgress {schedule} />
							</div>
						{/if}
					</div>
				{/each}
			</div>
		{/if}
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
		max-height: 70vh;
		overflow-y: auto;
	}

	.empty {
		text-align: center;
		padding: 40px;
		color: var(--text-light);
	}

	.rows {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.row {
		border: 1px solid var(--border);
		border-radius: 8px;
		overflow: hidden;
	}

	.row-header {
		display: flex;
		align-items: center;
		gap: 12px;
		width: 100%;
		padding: 12px 16px;
		background: none;
		border: none;
		cursor: pointer;
		text-align: left;
		font-size: 14px;
	}

	.row-header:hover {
		background: var(--hover);
	}

	.chevron {
		display: inline-flex;
		color: var(--text-light);
		transition: transform 0.15s ease;
	}

	.chevron.rotated {
		transform: rotate(90deg);
	}

	.row-dates {
		flex: 1;
		font-weight: 500;
	}

	.row-body {
		padding: 16px;
		border-top: 1px solid var(--border);
	}
</style>
