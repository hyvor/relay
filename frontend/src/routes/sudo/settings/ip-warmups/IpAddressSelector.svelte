<script lang="ts">
	import {
		ActionList,
		ActionListGroup,
		ActionListItem,
		Button,
		Dropdown,
		LoadButton,
		Loader,
		toast
	} from '@hyvor/design/components';
	import type { IpAddress, Server } from '../../sudoTypes';
	import { getServers } from '../../sudoActions';
	import IconChevronDown from '@hyvor/icons/IconChevronDown';

	interface Props {
		selectedIp: IpAddress | null;
	}

	let { selectedIp = $bindable(null) }: Props = $props();

	const PER_PAGE = 10;

	let showDropdown = $state(false);
	let servers: Server[] = $state([]);
	let loaded = $state(false);
	let loading = $state(false);
	let loadingMore = $state(false);
	let hasMore = $state(true);

	function loadServers(more = false) {
		if (more) {
			loadingMore = true;
		} else {
			loading = true;
		}

		const beforeId = more && servers.length > 0 ? servers[servers.length - 1].id : null;

		getServers(null, PER_PAGE, beforeId)
			.then((res) => {
				servers = more ? [...servers, ...res] : res;
				hasMore = res.length === PER_PAGE;
				loaded = true;
			})
			.catch((err) => {
				toast.error('Failed to load servers: ' + err.message);
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	function handleTriggerClick() {
		if (!loaded) {
			loadServers();
		}
	}

	function handleSelect(ip: IpAddress | null) {
		selectedIp = ip;
		showDropdown = false;
	}
</script>

<Dropdown bind:show={showDropdown} width={300}>
	{#snippet trigger()}
		<Button color="input" on:click={handleTriggerClick}>
			{#snippet start()}
				IP Address
			{/snippet}

			<span class="selected-ip">
				{#if selectedIp}
					{selectedIp.ip_address}
				{:else}
					Any
				{/if}
			</span>
			{#snippet end()}
				<IconChevronDown size={10} />
			{/snippet}
		</Button>
	{/snippet}
	{#snippet content()}
		<div class="results">
			{#if loading}
				<div class="loading"><Loader size={16} /></div>
			{:else}
				<ActionList>
					<ActionListItem on:select={() => handleSelect(null)}>Any</ActionListItem>

					{#each servers as server (server.id)}
						{#if server.ip_addresses.length > 0}
							<ActionListGroup title={server.hostname}>
								{#each server.ip_addresses as ip (ip.id)}
									<ActionListItem on:select={() => handleSelect(ip)}>
										{ip.ip_address}
									</ActionListItem>
								{/each}
							</ActionListGroup>
						{/if}
					{/each}
				</ActionList>

				<LoadButton
					text="Load More"
					loading={loadingMore}
					show={hasMore}
					on:click={() => loadServers(true)}
				/>
			{/if}
		</div>
	{/snippet}
</Dropdown>

<style>
	.selected-ip {
		font-weight: normal;
	}
	.results {
		max-height: 350px;
		overflow-y: auto;
	}
	.loading {
		display: flex;
		justify-content: center;
		padding: 20px;
	}
</style>
