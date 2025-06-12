<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { Server } from '../adminTypes';
	import { ipAddressesStore } from '../adminStore';
	import WorkersTag from './WorkersTag.svelte';

	interface Props {
		server: Server;
	}

	let { server }: Props = $props();

	const ips = $derived($ipAddressesStore.filter((ip) => ip.server_id === server.id));
</script>

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

<div class="ips">
	{#each ips as ip}
		<div class="ip">
			<div class="ip-address">
				{ip.ip_address}
			</div>
			<div class="queue-name">
				<span class="queue-name-title"> Queue: </span>
				{#if ip.email_queue}
					{ip.email_queue}
				{:else}
					None
				{/if}
			</div>
			<div class="">
				<Tag size="small" color="green">Enabled</Tag>
			</div>
		</div>
	{/each}
</div>

<style>
	.row {
		display: flex;
		align-items: center;
		padding: 15px 25px;
		border-radius: 20px;
	}

	.ips {
		padding: 10px;
		margin-left: 35px;
		border-left: 2px solid var(--border);
	}
	.ip {
		padding: 5px;
		display: flex;
		align-items: center;
	}
	.ip-address {
		flex: 1;
	}
	.queue-name {
		flex: 1;
	}
	.queue-name-title {
		color: var(--text-light);
		font-size: 14px;
	}
</style>
