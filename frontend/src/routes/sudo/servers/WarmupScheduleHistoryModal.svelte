<script lang="ts">
	import {
		Modal, Accordion, Divider, Loader, DetailCard, DetailCards, Tag, toast
	} from '@hyvor/design/components';
	import { getWarmupSchedules } from '../sudoActions';
	import type { IpAddress, WarmupSchedule } from '../sudoTypes';

	interface Props {
		show: boolean;
		ip: IpAddress | null;
		onClose: () => void;
	}

	let { show = $bindable(), ip, onClose }: Props = $props();

	let schedules = $state<WarmupSchedule[]>([]);
	let loading = $state(false);

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

	function formatDate(timestamp: number): string {
		return new Date(timestamp * 1000).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	}

	function formatNumber(n: number): string {
		return n.toLocaleString();
	}
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
	loading={loading}
>
	<div class="modal-content">
		{#if loading}
			<Loader block size="large" padding="large" />
		{:else if schedules.length === 0}
			<div class="empty">No warmup schedules found for this IP.</div>
		{:else}
			{#each schedules as schedule, i}
				<div class="schedule-entry">
					<div class="entry-header">
						<span class="entry-title">Schedule #{schedules.length - i}</span>
						<span class="entry-date">{formatDate(schedule.created_at)}</span>
						{#if schedule.is_warming_up}
							<Tag color="orange" size="small">Warming</Tag>
						{:else}
							<Tag color="green" size="small">Warmed</Tag>
						{/if}
					</div>

					{#if schedule.warmup_started_date}
						<div class="entry-detail">
							Started: {formatDate(schedule.warmup_started_date)}
						</div>
					{/if}

					<div class="entry-stats">
						<DetailCards min={120}>
							<DetailCard label="Today's Sends">
								{formatNumber(schedule.warmup_sent_today)}
							</DetailCard>
							<DetailCard label="Daily Max">
								{formatNumber(schedule.warmup_max_today)}
							</DetailCard>
							<DetailCard label="Status">
								{schedule.warmup_status}
							</DetailCard>
						</DetailCards>
					</div>

					{#if schedule.warmup_schedule && schedule.warmup_schedule.length > 0}
						<div class="accordion-wrapper">
							<Accordion title="View 30-Day Schedule">
								<div class="schedule-grid">
									{#each schedule.warmup_schedule as value, day (day)}
										<div class="day-cell">
											<span class="day-label">Day {day + 1}</span>
											<span class="day-value">{formatNumber(value)}</span>
										</div>
									{/each}
								</div>
							</Accordion>
						</div>
					{/if}
				</div>

				{#if i < schedules.length - 1}
					<Divider margin={16} />
				{/if}
			{/each}
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

	.schedule-entry {
		padding: 16px 0;
	}

	.entry-header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 8px;
	}

	.entry-title {
		font-weight: 600;
		font-size: 16px;
	}

	.entry-date {
		color: var(--text-light);
		font-size: 13px;
		flex: 1;
	}

	.entry-detail {
		font-size: 13px;
		color: var(--text-light);
		margin-bottom: 12px;
	}

	.entry-stats {
		margin-bottom: 12px;
	}

	.accordion-wrapper {
		margin-top: 8px;
	}

	.schedule-grid {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		gap: 8px;
	}

	.day-cell {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 6px;
		background: var(--bg-input);
		border-radius: 6px;
	}

	.day-label {
		font-size: 11px;
		color: var(--text-light);
	}

	.day-value {
		font-size: 14px;
		font-weight: 600;
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
