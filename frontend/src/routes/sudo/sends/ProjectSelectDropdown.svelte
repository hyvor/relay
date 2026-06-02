<script lang="ts">
	import {
		Button,
		Dropdown,
		IconButton,
		Loader,
		TextInput,
		toast
	} from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconX from '@hyvor/icons/IconX';
	import { tick } from 'svelte';
	import { getProjects } from '../sudoActions';
	import type { Organization, SudoProject } from '../sudoTypes';

	interface Props {
		value?: SudoProject | null;
	}

	let { value = $bindable(null) }: Props = $props();

	const LIMIT = 10;
	const DEBOUNCE_MS = 300;

	let input = $state('');
	let results: SudoProject[] = $state([]);
	let orgsMap: Map<number, Organization> = $state(new Map());
	let loading = $state(false);
	let searched = $state(false);

	let show = $state(false);
	let inputEl: HTMLInputElement;
	let timeoutId: ReturnType<typeof setTimeout> | null = null;

	async function handleTriggerClick() {
		await tick();
		if (show) {
			inputEl?.focus();
		}
	}

	function handleKeyup() {
		const q = input.trim();
		if (!q) {
			results = [];
			searched = false;
			loading = false;
			return;
		}

		if (timeoutId) {
			clearTimeout(timeoutId);
		}

		loading = true;
		timeoutId = setTimeout(doSearch, DEBOUNCE_MS);
	}

	async function doSearch() {
		const q = input.trim();
		if (!q) return;
		loading = true;
		try {
			const res = await getProjects(q, LIMIT, null);
			results = res.projects;
			const newMap = new Map<number, Organization>();
			for (const org of res.orgs) {
				newMap.set(org.id, org);
			}
			orgsMap = newMap;
		} catch (error) {
			toast.error('Failed to load projects: ' + (error as Error).message);
			results = [];
			searched = false;
		} finally {
			loading = false;
			searched = true;
		}
	}

	function select(project: SudoProject) {
		value = project;
		show = false;
		input = '';
		results = [];
		searched = false;
	}

	function clear(e: MouseEvent) {
		e.stopPropagation();
		value = null;
		input = '';
		results = [];
		searched = false;
		loading = false;
	}
</script>

<Dropdown bind:show width={320}>
	{#snippet trigger()}
		<Button size="small" color="input" on:click={handleTriggerClick}>
			<span class="name">Project</span>
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
			placeholder="Search projects..."
			on:keyup={handleKeyup}
			bind:input={inputEl}
			block
		>
			{#snippet end()}
				{#if loading}
					<Loader size={14} />
				{/if}
			{/snippet}
		</TextInput>

		{#if searched}
			<div class="results">
				{#if results.length === 0}
					<div class="empty">No projects match your search.</div>
				{:else}
					{#each results as project (project.id)}
						<button class="result-item" onclick={() => select(project)}>
							<div class="project-name">{project.name}</div>
							<div class="project-meta">
								#{project.id}
								{#if project.organization_id !== null && orgsMap.get(project.organization_id)}
									· {orgsMap.get(project.organization_id)?.name}
								{:else if project.organization_id !== null}
									· org {project.organization_id}
								{/if}
								· {project.send_type}
							</div>
						</button>
					{/each}
				{/if}
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
	.project-name {
		font-weight: 600;
		font-size: 14px;
	}
	.project-meta {
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
