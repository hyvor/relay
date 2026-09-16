<script lang="ts">
	import {
		ActionList,
		ActionListGroup,
		ActionListItem,
		Button,
		Dropdown
	} from '@hyvor/design/components';
	import type { IpAddress } from '../../sudoTypes';
	import { serversStore } from '../../sudoStore';
	import IconChevronDown from '@hyvor/icons/IconChevronDown';

	interface Props {
		selectedIp: IpAddress | null;
	}

	let { selectedIp = $bindable(null) }: Props = $props();

	let showDropdown = $state(false);

	function handleSelect(ip: IpAddress | null) {
		selectedIp = ip;
		showDropdown = false;
	}
</script>

<Dropdown bind:show={showDropdown} width={300}>
	{#snippet trigger()}
		<Button color="input">
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
			<ActionList>
				<ActionListItem on:select={() => handleSelect(null)}>Any</ActionListItem>

				{#each $serversStore as server (server.id)}
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
</style>
