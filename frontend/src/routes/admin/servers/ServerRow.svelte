<script lang="ts">
	import { TabNav, TabNavItem, Tag } from '@hyvor/design/components';
	import type { Server } from '../adminTypes';
	import { ipAddressesStore } from '../adminStore';
	import WorkersTag from './WorkersTag.svelte';
	import WorkerSplit from './WorkerSplit.svelte';
	import IpRow from './IpRow.svelte';

	interface Props {
		server: Server;
	}

	let { server }: Props = $props();

	const ips = $derived($ipAddressesStore.filter((ip) => ip.server_id === server.id));

	let activeTab: 'ips' | 'workers' = $state('ips');
</script>

<div class="wrap hds-box">
	<div class="row">
		<div class="id">
			({server.id})
		</div>
		<div class="hostname">
			{server.hostname}
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
			<TabNavItem name="workers">Workers</TabNavItem>
		</TabNav>
	</div>

	{#if activeTab === 'ips'}
		<div class="ips">
			<table>
				<thead>
					<tr>
						<th>IP Address</th>
						<th>Queue</th>
						<th>PTR</th>
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

	{#if activeTab === 'workers'}
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
</style>
