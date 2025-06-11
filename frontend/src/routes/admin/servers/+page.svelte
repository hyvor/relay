<script lang="ts">
	import { onMount } from 'svelte';
	import { Loader, toast } from '@hyvor/design/components';
	import { getServers } from '../adminActions';
	import type { Server } from '../adminTypes';
	import ServerRow from './ServerRow.svelte';

	let loading = $state(false);
	let servers: Server[] = $state([]);

	onMount(() => {
		getServers()
			.then((response) => {
				servers = response;
			})
			.catch((err) => {
				toast.error('Failed to load servers: ' + err.message);
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
			<ServerRow {server} />
		{/each}
	</div>
{/if}

<style>
	.server-list {
		padding: 20px;
	}
</style>
