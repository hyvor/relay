<script lang="ts" generics="Key extends HealthCheckName">
	import { Tag, Callout } from '@hyvor/design/components';
	import type { HealthCheckResult, HealthCheckName, HealthCheckData } from '../sudoTypes';
	import dayjs from 'dayjs';
	import relativeTime from 'dayjs/plugin/relativeTime';

	dayjs.extend(relativeTime);

	interface Props {
		checkKey: Key;
		result: HealthCheckResult<Key>;
	}

	let { checkKey, result }: Props = $props();

	function formatCheckName(key: HealthCheckName): string {
		return {
			all_queues_have_at_least_one_ip: 'All queues have at least one IP',
			all_active_ips_have_correct_ptr:
				'All active IPs have correct PTR records (Forward and Reverse)',
			instance_dkim_correct: 'Instance DKIM is correct',
			all_ips_are_in_spf_record: 'All IPs are included in SPF record',
			all_servers_can_be_reached_via_private_network:
				'All servers can be reached via private network',
			none_of_the_ips_are_on_known_blacklists: 'None of the IPs are on known blacklists'
		}[key]!;
	}

	function formatCheckedTime(checkedAt: string): string {
		return dayjs(checkedAt).fromNow();
	}

	function renderFailureData(data: HealthCheckData[Key]): string {
		if (checkKey === 'all_active_ips_have_correct_ptr') {
			const ptrData = data as HealthCheckData['all_active_ips_have_correct_ptr'];
			return `Invalid PTRs: ${ptrData.invalid_ptrs
				.map(
					(ptr) =>
						`${ptr.ip} (Forward: ${ptr.forward_valid ? 'Valid' : 'Invalid'}, Reverse: ${ptr.reverse_valid ? 'Valid' : 'Invalid'})`
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

		if (checkKey === 'all_servers_can_be_reached_via_private_network') {
			const unreachableServers =
				data as HealthCheckData['all_servers_can_be_reached_via_private_network'];
			return `Unreachable servers: ${unreachableServers.unreachable_servers.join(', ')}`;
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
