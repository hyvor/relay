<script lang="ts">
	import { Button, Tag, confirm, toast } from '@hyvor/design/components';
	import IconChevronRight from '@hyvor/icons/IconChevronRight';
	import { updateWarmupSchedule, deleteWarmupSchedule } from '../../sudoActions';
	import { warmupSchedulesStore } from '../../sudoStore';
	import WarmupScheduleProgress from './WarmupScheduleProgress.svelte';
	import type { WarmupSchedule, WarmupStatus } from '../../sudoTypes';
	import { slide } from 'svelte/transition';

	interface Props {
		schedule: WarmupSchedule;
	}

	let { schedule }: Props = $props();

	const ipAddress = $derived(schedule.ip_address);

	const TOTAL_DAYS = 30;

	let expanded = $state(false);
	let cancelling = $state(false);
	let deleting = $state(false);

	let currentDay = $derived(Math.min(schedule.results.length + 1, TOTAL_DAYS));
	let progressPercentage = $derived(Math.round((currentDay / TOTAL_DAYS) * 100));

	const statusColors: Record<WarmupStatus, 'orange' | 'green' | 'red'> = {
		warming: 'orange',
		warmed: 'green',
		cancelled: 'red'
	};

	function formatDate(timestamp: number): string {
		return new Date(timestamp * 1000).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}

	function endDate(): string {
		const end = new Date(schedule.started_date * 1000);
		end.setDate(end.getDate() + TOTAL_DAYS);
		return formatDate(Math.floor(end.getTime() / 1000));
	}

	function toggle() {
		expanded = !expanded;
	}

	async function handleCancel(event: MouseEvent) {
		event.stopPropagation();

		const confirmed = await confirm({
			title: 'Cancel Warmup Schedule',
			content: `Are you sure you want to cancel the warmup schedule for IP ${ipAddress}? The IP will no longer be rate limited.`,
			confirmText: 'Cancel Warmup',
			cancelText: 'Keep Warming',
			danger: true,
			autoClose: false
		});

		if (!confirmed) return;

		confirmed.loading();
		cancelling = true;

		try {
			const updated = await updateWarmupSchedule(schedule.id, { status: 'cancelled' });
			warmupSchedulesStore.update((schedules) =>
				schedules.map((s) => (s.id === updated.id ? updated : s))
			);
			toast.success(`Warmup cancelled for IP ${ipAddress}`);
		} catch (error: any) {
			toast.error('Failed to cancel warmup: ' + error.message);
		} finally {
			cancelling = false;
			confirmed.close();
		}
	}

	async function handleDelete(event: MouseEvent) {
		event.stopPropagation();

		const confirmed = await confirm({
			title: 'Delete Warmup Schedule',
			content: `Are you sure you want to delete this warmup schedule for IP ${ipAddress}? This action cannot be undone.`,
			confirmText: 'Delete',
			cancelText: 'Cancel',
			danger: true,
			autoClose: false
		});

		if (!confirmed) return;

		confirmed.loading();
		deleting = true;

		try {
			await deleteWarmupSchedule(schedule.id);
			warmupSchedulesStore.update((schedules) =>
				schedules.filter((s) => s.id !== schedule.id)
			);
			toast.success('Warmup schedule deleted');
		} catch (error: any) {
			toast.error('Failed to delete warmup schedule: ' + error.message);
		} finally {
			deleting = false;
			confirmed.close();
		}
	}
</script>

<div class="row" class:open={expanded}>
	<button class="row-header" onclick={toggle}>
		<span class="chevron" class:rotated={expanded}>
			<IconChevronRight size={12} />
		</span>

		<div>
			<div class="ip-status">
				<span class="ip">{ipAddress}</span>

				<Tag color={statusColors[schedule.status]} size="small">
					<span class="status-tag">{schedule.status}</span>
				</Tag>
			</div>

			<div class="dates-progress">
				<span class="dates">
					{formatDate(schedule.started_date)} &ndash; {endDate()}
				</span>

				{#if schedule.status === 'warming'}
					<span class="progress">
						<span class="progress-label">Day {currentDay} of {TOTAL_DAYS}</span>
						<span class="progress-track">
							<span class="progress-fill" style="width: {progressPercentage}%"></span>
						</span>
					</span>
				{/if}
			</div>
		</div>

		<span class="actions">
			{#if schedule.status === 'warming'}
				<Button
					size="small"
					color="red"
					variant="outline"
					disabled={cancelling}
					on:click={handleCancel}
				>
					Cancel
				</Button>
			{/if}
			{#if schedule.status !== 'warming'}
				<Button
					size="small"
					color="red"
					variant="fill-light"
					disabled={deleting}
					on:click={handleDelete}
				>
					Delete
				</Button>
			{/if}
		</span>
	</button>

	{#if expanded}
		<div class="row-body" transition:slide={{ duration: 150 }}>
			<WarmupScheduleProgress {schedule} />
		</div>
	{/if}
</div>

<style>
	.row {
		border: 1px solid var(--border);
		border-radius: 20px;
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
		flex-wrap: wrap;
		transition: background-color 0.15s ease;
	}

	.row-header:hover {
		background-color: var(--hover);
	}

	.row.open .row-header {
		background-color: var(--hover);
	}

	.chevron {
		display: inline-flex;
		color: var(--text-light);
		transition: transform 0.15s ease;
		flex-shrink: 0;
	}

	.chevron.rotated {
		transform: rotate(90deg);
	}

	.ip-status {
		display: flex;
		align-items: center;
		gap: 5px;
	}

	.ip {
		font-weight: 600;
	}

	.dates-progress {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 5px;
		gap: 20px;
	}

	.dates {
		color: var(--text-light);
		font-size: 13px;
	}

	.progress {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 12px;
		color: var(--text-light);
		min-width: 140px;
	}

	.progress-track {
		width: 80px;
		height: 6px;
		background: var(--input);
		border-radius: 3px;
		overflow: hidden;
	}

	.progress-fill {
		display: block;
		height: 100%;
		background: var(--orange);
		border-radius: 3px;
	}

	.actions {
		display: flex;
		gap: 6px;
		margin-left: auto;
	}

	.row-body {
		padding: 16px;
		border-top: 1px solid var(--border);
	}

	.status-tag {
		text-transform: capitalize;
	}
</style>
