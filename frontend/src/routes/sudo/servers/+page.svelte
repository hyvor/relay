<script lang="ts">
	import {
		IconButton,
		IconMessage,
		LoadButton,
		Loader,
		TextInput,
		toast
	} from '@hyvor/design/components';
	import IconX from '@hyvor/icons/IconX';
	import { getServers } from '../sudoActions';
	import ServerRow from './ServerRow.svelte';
	import type { Server } from '../sudoTypes';
	import SingleBox from '../SingleBox.svelte';

	const PER_PAGE = 10;

	let nameInput = $state('');
	let nameSearch = $state('');

	let servers: Server[] = $state([]);
	let loading = $state(true);
	let loadingMore = $state(false);
	let hasMore = $state(true);

	function load(more = false) {
		if (more) {
			loadingMore = true;
		} else {
			loading = true;
		}

		const beforeId = more && servers.length > 0 ? servers[servers.length - 1].id : null;

		getServers(nameSearch || null, PER_PAGE, beforeId)
			.then((res) => {
				servers = more ? [...servers, ...res] : res;
				hasMore = res.length === PER_PAGE;
			})
			.catch((err) => {
				toast.error('Failed to load servers: ' + err.message);
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	$effect(() => {
		load();
	});

	function applyName() {
		if (nameSearch !== nameInput.trim()) {
			nameSearch = nameInput.trim();
		}
	}

	function clearName() {
		nameInput = '';
		nameSearch = '';
	}
</script>

<SingleBox>
	<div class="top">
		<TextInput
			bind:value={nameInput}
			placeholder="Search by hostname"
			style="width:280px"
			on:keydown={(e: KeyboardEvent) => e.key === 'Enter' && applyName()}
			on:blur={applyName}
			size="small"
			block={false}
		>
			{#snippet end()}
				{#if nameInput.trim() !== ''}
					<IconButton variant="invisible" color="gray" size={16} on:click={clearName}>
						<IconX size={12} />
					</IconButton>
				{/if}
			{/snippet}
		</TextInput>

		{#if nameSearch !== nameInput.trim()}
			<span class="press-enter">&crarr;</span>
		{/if}
	</div>

	{#if loading}
		<Loader full />
	{:else if servers.length === 0}
		<IconMessage empty message="No servers found" />
	{:else}
		{#each servers as server (server.id)}
			<ServerRow {server} />
		{/each}

		<LoadButton text="Load More" loading={loadingMore} show={hasMore} on:click={() => load(true)} />
	{/if}
</SingleBox>

<style>
	.top {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 15px 35px;
		border-bottom: 1px solid var(--border);
	}
	.press-enter {
		color: var(--text-light);
		font-size: 14px;
	}
</style>
