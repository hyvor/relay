<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import IconCheck from '@hyvor/icons/IconCheck';
	import type { WarmupSchedule } from '../sudoTypes';

	interface Props {
		schedule: WarmupSchedule;
	}

	let { schedule }: Props = $props();

	interface Day {
		day: number;
		limit: number;
		sent: number | null;
		percentage: number | null;
		completed: boolean;
		isCurrent: boolean;
	}

	let completedDays = $derived(schedule.results.length);

	let days = $derived<Day[]>(
		schedule.schedule.map((limit, i) => {
			const completed = i < completedDays;
			const isCurrent = schedule.status === 'warming' && i === completedDays;

			let sent: number | null = null;
			if (completed) {
				sent = schedule.results[i];
			} else if (isCurrent) {
				sent = schedule.sent_today;
			}

			const percentage =
				sent !== null && limit > 0 ? Math.min(100, Math.round((sent / limit) * 100)) : null;

			return { day: i + 1, limit, sent, percentage, completed, isCurrent };
		})
	);

	function formatNumber(n: number): string {
		return n.toLocaleString();
	}
</script>

<div class="days">
	{#each days as day (day.day)}
		<div class="day" class:current={day.isCurrent} class:completed={day.completed}>
			<div class="day-head">
				<span class="day-label">Day {day.day}</span>
				{#if day.completed}
					<span class="check"><IconCheck size={14} /></span>
				{:else if day.isCurrent}
					<Tag color="orange" size="x-small">Current</Tag>
				{/if}
			</div>
			<div class="day-limit">{formatNumber(day.limit)}</div>
			{#if day.sent !== null}
				<div class="day-sent">
					{formatNumber(day.sent)} sent
					{#if day.percentage !== null}
						<span class="day-percentage">({day.percentage}%)</span>
					{/if}
				</div>
			{/if}
		</div>
	{/each}
</div>

<style>
	.days {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		gap: 8px;
	}

	.day {
		display: flex;
		flex-direction: column;
		gap: 4px;
		padding: 10px;
		background: var(--bg-input);
		border-radius: 8px;
		border: 1px solid transparent;
	}

	.day.current {
		background: var(--orange-light);
		border-color: var(--orange);
	}

	.day.completed {
		background: var(--green-light);
	}

	.day-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 4px;
	}

	.day-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: 500;
	}

	.check {
		display: inline-flex;
		color: var(--green);
	}

	.day-limit {
		font-size: 15px;
		font-weight: 600;
	}

	.day-sent {
		font-size: 12px;
		color: var(--text-light);
	}

	.day-percentage {
		color: var(--text-light);
	}

	@media (max-width: 900px) {
		.days {
			grid-template-columns: repeat(3, 1fr);
		}
	}

	@media (max-width: 600px) {
		.days {
			grid-template-columns: repeat(2, 1fr);
		}
	}
</style>
