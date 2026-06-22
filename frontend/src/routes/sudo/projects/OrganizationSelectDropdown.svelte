<script lang="ts">
	import {
		Button,
		Dropdown,
		IconButton,
		LoadButton,
		Loader,
		TextInput,
		toast
	} from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconX from '@hyvor/icons/IconX';
	import { flagByCountryCode } from '$lib/helpers/countryCode';
	import { tick } from 'svelte';
	import { getProjectOrganizations } from '../sudoActions';
	import type { Organization } from '../sudoTypes';

	interface Props {
		value?: Organization | null;
	}

	let { value = $bindable(null) }: Props = $props();

	const PER_PAGE = 50;

	let input = $state('');
	let organizations: Organization[] = $state([]);
	let loaded = $state(false);
	let loading = $state(false);
	let loadingMore = $state(false);
	let hasMore = $state(true);

	let show = $state(false);
	let inputEl: HTMLInputElement;

	// Search filters only the already-loaded page(s): organization names live in
	// the auth service and cannot be searched server-side.
	let results = $derived.by(() => {
		const q = input.trim().toLowerCase();
		if (q === '') return organizations;
		return organizations.filter(
			(org) => org.name.toLowerCase().includes(q) || String(org.id).includes(q)
		);
	});

	async function handleTriggerClick() {
		await tick();
		if (show) {
			inputEl?.focus();
			if (!loaded) {
				loadOrganizations();
			}
		}
	}

	async function loadOrganizations(more = false) {
		if (more) {
			loadingMore = true;
		} else {
			loading = true;
		}

		const beforeId = more && organizations.length > 0 ? organizations[organizations.length - 1].id : null;

		try {
			const page = await getProjectOrganizations(PER_PAGE, beforeId);
			organizations = more ? [...organizations, ...page] : page;
			hasMore = page.length === PER_PAGE;
			loaded = true;
		} catch (error) {
			toast.error('Failed to load organizations: ' + (error as Error).message);
		} finally {
			loading = false;
			loadingMore = false;
		}
	}

	function select(org: Organization) {
		value = org;
		show = false;
		input = '';
	}

	function clear(e: MouseEvent) {
		e.stopPropagation();
		value = null;
		input = '';
	}
</script>

<Dropdown bind:show width={320}>
	{#snippet trigger()}
		<Button size="small" color="input" on:click={handleTriggerClick}>
			<span class="name">Organization</span>
			<span class="val">
				{value ? value.name : 'All'}
			</span>

			{#if value}
				<IconButton size={14} style="margin-left:4px;" color="gray" on:click={clear}>
					<IconX size={10} />
				</IconButton>
			{/if}

			{#snippet end()}
				<IconCaretDown size={12} />
			{/snippet}
		</Button>
	{/snippet}

	{#snippet content()}
		<TextInput
			bind:value={input}
			placeholder="Search organizations..."
			bind:input={inputEl}
			block
		>
			{#snippet end()}
				{#if loading}
					<Loader size={14} />
				{/if}
			{/snippet}
		</TextInput>

		{#if loaded}
			<div class="results">
				{#if results.length === 0}
					<div class="empty">No organizations found.</div>
				{:else}
					{#each results as org (org.id)}
						<button class="result-item" onclick={() => select(org)}>
							<div class="org-name">
								{org.name}
								{#if org.billing_address?.country}
									<span title={org.billing_address.country}>
										{flagByCountryCode(org.billing_address.country)}
									</span>
								{/if}
							</div>
							<div class="org-meta">
								#{org.id}
								{#if org.billing_email}
									· {org.billing_email}
								{/if}
							</div>
						</button>
					{/each}
				{/if}

				<LoadButton
					text="Load More"
					loading={loadingMore}
					show={hasMore}
					on:click={() => loadOrganizations(true)}
				/>
			</div>
		{/if}
	{/snippet}
</Dropdown>

<style>
	.name {
		margin-right: 6px;
	}
	.val {
		font-weight: normal;
		font-size: 13px;
	}
	.results {
		overflow: hidden;
		margin-top: 6px;
		max-height: 350px;
		overflow-y: auto;
	}
	.result-item {
		display: block;
		width: 100%;
		text-align: left;
		padding: 8px 12px;
		border-radius: 6px;
		background: transparent;
		border: none;
		cursor: pointer;
	}
	.result-item:hover {
		background: var(--hover);
	}
	.org-name {
		font-weight: 600;
		font-size: 14px;
	}
	.org-meta {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 2px;
	}
	.empty {
		padding: 12px;
		font-size: 14px;
		color: var(--text-light);
	}
</style>
