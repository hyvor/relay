<script lang="ts">
	import { onMount } from 'svelte';
	import { Loader, toast } from '@hyvor/design/components';
	import { getIpAddresses, getServers } from '../adminActions';
	import type { IpAddress, Server } from '../adminTypes';
	import ServerRow from './ServerRow.svelte';

	let loading = $state(false);
	let servers: Server[] = $state([]);
	let ips: IpAddress[] = $state([]);

	function getIpAddressesOfServer(server: Server): IpAddress[] {
		return ips.filter((ip) => ip.server_id === server.id);
	}

	onMount(async () => {
		Promise.all([await getServers(), await getIpAddresses()])
			.then(([serversResponse, ipsResponse]) => {
				servers = serversResponse;
				ips = ipsResponse;
			})
			.catch((err) => {
				toast.error('Failed to load data: ' + err.message);
			})
			.finally(() => {
				loading = false;
			});
	});
</script>

{#if loading}
	<Loader full />
{:else}
	<div class="server-list">
		{#each servers as server}
			<ServerRow {server} ips={getIpAddressesOfServer(server)} />
		{/each}
	</div>
{/if}

<style>
	.server-list {
		padding: 20px;
	}
</style>
