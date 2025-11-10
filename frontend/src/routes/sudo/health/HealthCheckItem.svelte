<script lang="ts" generics="Key extends HealthCheckName">
	import { Tag, Callout } from '@hyvor/design/components';
	import type { HealthCheckResult, HealthCheckName, HealthCheckData } from '../sudoTypes';
	import dayjs from 'dayjs';
	import relativeTime from 'dayjs/plugin/relativeTime';
	import BlacklistDebug from './BlacklistDebug.svelte';
	import { formatCheckName } from './formatHealthChecks';

	dayjs.extend(relativeTime);

	interface Props {
		checkKey: Key;
		result: HealthCheckResult<Key>;
	}

	let { checkKey, result }: Props = $props();

	function formatCheckedTime(checkedAt: string): string {
		return dayjs(checkedAt).fromNow();
	}

	function renderFailureData(data: HealthCheckData[Key]): string {
		if (checkKey === 'all_active_ips_have_correct_ptr') {
			const ptrData = data as HealthCheckData['all_active_ips_have_correct_ptr'];

			function getValidInvalidMsg(valid: boolean, error: string | null): string {
				if (valid) {
					return 'OK';
				} else {
					return 'invalid' + (error ? ` (${error})` : '');
				}
			}

			return `Invalid PTRs: ${ptrData.invalid_ptrs
				.map(
					(ptr) =>
						`${ptr.ip}: forward ${getValidInvalidMsg(ptr.forward_valid, ptr.forward_error)}, reverse ${getValidInvalidMsg(ptr.reverse_valid, ptr.reverse_error)}`
				)
				.join(', ')}`;
		}

		if (checkKey === 'all_queues_have_at_least_one_ip') {
			const queueData = data as HealthCheckData['all_queues_have_at_least_one_ip'];
			return `Queues without IP: ${queueData.queues_without_ip.join(', ')}`;
		}

		if (checkKey === 'instance_dkim_correct') {
			const dkimData = data as HealthCheckData['instance_dkim_correct'];
			return (
				`Instance DKIM is not correct. Error: ${dkimData.error}` +
				(dkimData.expected ? `, Expected: ${dkimData.expected}` : '') +
				(dkimData.actual ? `, Actual: ${dkimData.actual}` : '')
			);
		}

		if (checkKey === 'all_ips_are_in_spf_record') {
			const spfData = data as HealthCheckData['all_ips_are_in_spf_record'];
			return `Invalid IPs: ${spfData.invalid_ips.join(', ')}`;
		}

		if (checkKey === 'none_of_the_ips_are_on_known_blacklists') {
			const blacklistData =
				data as HealthCheckData['none_of_the_ips_are_on_known_blacklists'];
			const lists = blacklistData.lists;
			const blacklistedIps = [];

			for (const [listName, listData] of Object.entries(lists)) {
				const blacklistedIpsOnList = [];
				for (const [ip, entry] of Object.entries(listData)) {
					if (entry.status === 'blocked') {
						blacklistedIpsOnList.push(ip);
					}
				}
				if (blacklistedIpsOnList.length > 0) {
					blacklistedIps.push(`${listName}: ${blacklistedIpsOnList.join(', ')}`);
				}
			}

			return `Blacklisted IPs: ${blacklistedIps.join(', ')}`;
		}

		if (checkKey === 'no_unread_infrastructure_bounces') {
			const bounceData = data as HealthCheckData['no_unread_infrastructure_bounces'];
			const count = bounceData.unread_count;

			return `${count} unread infrastructure bounce${count > 1 ? 's' : ''} found. <a href="/sudo/debug/infrastructure-bounces">View infrastructure bounces</a>.`;
		}

		return JSON.stringify(data);
	}
</script>

<div class="check">
	<div class="dot" class:passed={result.passed} class:failed={!result.passed}></div>
	<div class="content">
		<div class="check-name">{formatCheckName(checkKey)}</div>
		<div class="check-details">
			<span class="checked-time">
				Checked {formatCheckedTime(result.checked_at)} &bull;
				{result.duration_ms}ms
			</span>
			{#if !result.passed && result.data}
				<div class="failure-callout">
					<Callout type="danger" size="small">
						{@html renderFailureData(result.data)}
					</Callout>
				</div>
			{/if}

			{#if checkKey === 'none_of_the_ips_are_on_known_blacklists'}
				<BlacklistDebug data={result.data as any} />
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

	.failure-callout :global(a) {
		text-decoration: underline;
	}

	.status {
		flex-shrink: 0;
	}
</style>
