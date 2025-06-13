<script lang="ts">
	import { onMount } from 'svelte';
	import { Loader, toast } from '@hyvor/design/components';
	import { getIpAddresses, getServers } from '../adminActions';
	import ServerRow from './ServerRow.svelte';
	import { ipAddressesStore, serversStore } from '../adminStore';

	let loading = $state(false);

	onMount(async () => {
		Promise.all([await getServers(), await getIpAddresses()])
			.then(([serversResponse, ipsResponse]) => {
				serversStore.set(serversResponse);
				ipAddressesStore.set(ipsResponse);
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
	{#each $serversStore as server}
		<ServerRow {server} />
	{/each}
{/if}
