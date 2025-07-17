<script lang="ts">
	import { Tag, Callout } from '@hyvor/design/components';
	import type { HealthCheckResult, HealthCheckQueueData, HealthCheckPtrData } from '../sudoTypes';
	import dayjs from 'dayjs';
	import relativeTime from 'dayjs/plugin/relativeTime';

	dayjs.extend(relativeTime);

	interface Props {
		checkKey: 'all_queues_have_at_least_one_ip' | 'all_active_ips_have_correct_ptr';
		result: HealthCheckResult;
	}

	let { checkKey, result }: Props = $props();

	function formatCheckName(key: string): string {
		return {
			all_queues_have_at_least_one_ip: 'All queues have at least one IP',
			all_active_ips_have_correct_ptr:
				'All active IPs have correct PTR records (Forward and Reverse)'
		}[key]!;
	}

	function formatCheckedTime(checkedAt: string): string {
		return dayjs(checkedAt).fromNow();
	}

	function renderFailureData(data: any): string {
		if (!data || Object.keys(data).length === 0) {
			return '';
		}

		if (data.queues_without_ip) {
			const queueData = data as HealthCheckQueueData;
			return `Queues without IP: ${queueData.queues_without_ip.join(', ')}`;
		}

		if (data.invalid_ptrs) {
			const ptrData = data as HealthCheckPtrData;
			return `Invalid PTRs: ${ptrData.invalid_ptrs
				.map(
					(ptr) =>
						`${ptr.ip} (Forward: ${ptr.forward_valid ? 'Valid' : 'Invalid'}, Reverse: ${ptr.reverse_valid ? 'Valid' : 'Invalid'})`
				)
				.join(', ')}`;
		}

		return JSON.stringify(data);
	}
</script>

<div class="check">
	<div class="dot" class:passed={result.passed} class:failed={!result.passed}></div>
	<div class="content">
		<div class="check-name">{formatCheckName(checkKey)}</div>
		<div class="check-details">
			<span class="checked-time">Checked {formatCheckedTime(result.checked_at)}</span>
			{#if !result.passed && result.data}
				<div class="failure-callout">
					<Callout type="danger" size="small">
						{renderFailureData(result.data)}
					</Callout>
				</div>
			{/if}
		</div>
	</div>
	<div class="status">
		<Tag size="small" color={result.passed ? 'green' : 'red'}>
			{result.passed ? 'Passed' : 'Failed'}
		</Tag>
	</div>
</div>

<style>
	.check {
		display: flex;
		align-items: flex-start;
		gap: 12px;
		padding: 12px 0;
		border-bottom: 1px solid var(--border-light);
	}

	.check:last-child {
		border-bottom: none;
	}

	.dot {
		width: 12px;
		height: 12px;
		border-radius: 50%;
		margin-top: 2px;
		flex-shrink: 0;
	}

	.dot.passed {
		background-color: var(--green);
	}

	.dot.failed {
		background-color: var(--red);
	}

	.content {
		flex: 1;
	}

	.check-name {
		font-size: 14px;
		font-weight: 500;
		color: var(--text);
		margin-bottom: 4px;
	}

	.check-details {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.checked-time {
		font-size: 12px;
		color: var(--text-light);
	}

	.failure-callout {
		font-size: 14px;
		margin-top: 10px;
	}

	.status {
		flex-shrink: 0;
	}
</style>
