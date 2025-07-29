<script lang="ts">
	import { TabNav, TabNavItem, Tag, Tooltip } from '@hyvor/design/components';
	import type { Server } from '../sudoTypes';
	import { ipAddressesStore } from '../sudoStore';
	import WorkersTag from './WorkersTag.svelte';
	import WorkerSplit from './WorkerSplit.svelte';
	import IpRow from './IpRow.svelte';
	import dayjs from 'dayjs';
	import IconBoxArrowUpRight from '@hyvor/icons/IconBoxArrowUpRight';

	interface Props {
		server: Server;
	}

	let { server }: Props = $props();

	const ips = $derived($ipAddressesStore.filter((ip) => ip.server_id === server.id));

	let activeTab: 'ips' | 'settings' = $state('ips');

	function getReadablePing() {
		if (!server.last_ping_at) return 'N/A';
		return dayjs.unix(server.last_ping_at).fromNow();
	}
</script>

<div class="wrap hds-box" class:dead={!server.is_alive}>
	<div class="row">
		<div class="id">
			({server.id})
		</div>
		<div class="hostname">
			{server.hostname}

			<Tag size="small">
				Private IP: {server.private_ip ? server.private_ip : '~'}
			</Tag>

			{#if server.last_ping_at}
				{#if !server.is_alive}
					<Tooltip text="This server has not sent a heartbeat in the last 3 minutes.">
						<Tag color="red" size="small">Dead, last heartbeat {getReadablePing()}</Tag>
					</Tooltip>
				{:else}
					<Tag color="green" size="small">Pinged {getReadablePing()}</Tag>
				{/if}
			{/if}
		</div>
		<div class="work">
			<WorkersTag text="API" value={server.api_workers} />
			<WorkersTag text="Email" value={server.email_workers} />
			<WorkersTag text="Webhook" value={server.webhook_workers} />
		</div>
	</div>

	<div class="tabs">
		<TabNav bind:active={activeTab}>
			<TabNavItem name="ips">Ip Addresses</TabNavItem>
			<TabNavItem name="settings">Settings</TabNavItem>
		</TabNav>
	</div>

	{#if activeTab === 'ips'}
		<div class="ips">
			<table>
				<thead>
					<tr>
						<th>ID</th>
						<th>IP Address</th>
						<th>Queue</th>
						<th>
							PTR
							<a class="hds-link ptr-learn-more" href="/hosting" target="_blank">
								Learn more
								<IconBoxArrowUpRight size={12} />
							</a>
						</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					{#each ips as ip}
						<IpRow {ip} />
					{/each}
				</tbody>
			</table>
		</div>
	{/if}

	{#if activeTab === 'settings'}
		<div class="workers">
			<WorkerSplit worker="api" {server} />
			<WorkerSplit worker="email" {server} />
			<WorkerSplit worker="webhook" {server} />
		</div>
	{/if}
</div>

<style>
	.wrap {
		padding: 25px 35px;
		border-right: 25px solid var(--green-light);
		margin-bottom: 20px;
	}
	.wrap.dead {
		border-right-color: var(--red-light);
	}
	.row {
		display: flex;
		align-items: center;
		margin-bottom: 15px;
		border-radius: 20px;
	}
	.id {
		margin-right: 8px;
		color: var(--text-light);
		font-size: 14px;
	}
	.hostname {
		flex: 1;
		font-weight: 600;
	}

	.tabs {
		font-size: 14px;
	}

	.ips {
		padding: 20px 10px;
		overflow: auto;
	}

	.workers {
		padding: 10px;
	}

	table {
		width: 100%;
		border-collapse: collapse;
		font-size: 1rem;
	}
	th {
		padding: 0.75rem;
		text-align: left;
	}

	.ptr-learn-more {
		font-size: 14px;
		margin-left: 3px;
		font-weight: normal;
	}
</style>
