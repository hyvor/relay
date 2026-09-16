<script lang="ts">
	import { IconButton, IconMessage, TextInput } from '@hyvor/design/components';
	import IconX from '@hyvor/icons/IconX';
	import { serversStore } from '../sudoStore';
	import ServerRow from './ServerRow.svelte';
	import SingleBox from '../SingleBox.svelte';

	let nameInput = $state('');

	let filteredServers = $derived(
		nameInput.trim() === ''
			? $serversStore
			: $serversStore.filter((server) =>
					server.hostname.toLowerCase().includes(nameInput.trim().toLowerCase())
				)
	);

	function clearName() {
		nameInput = '';
	}
</script>

<SingleBox>
	<div class="top">
		<TextInput
			bind:value={nameInput}
			placeholder="Search by hostname"
			style="width:280px"
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
	</div>

	{#if filteredServers.length === 0}
		<IconMessage empty message="No servers found" />
	{:else}
		{#each filteredServers as server (server.id)}
			<ServerRow {server} />
		{/each}
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
</style>
