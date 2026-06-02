<script lang="ts">
	import { IconButton, IconMessage, LoadButton, Loader, TextInput } from '@hyvor/design/components';
	import IconX from '@hyvor/icons/IconX';
	import SingleBox from '../SingleBox.svelte';
	import ProjectRow from './ProjectRow.svelte';
	import OrganizationSelectDropdown from './OrganizationSelectDropdown.svelte';
	import { getProjects } from '../sudoActions';
	import type { Organization, SudoProject } from '../sudoTypes';

	const PER_PAGE = 25;

	// Typed (uncommitted) vs applied (committed) name filter.
	let nameInput = $state('');
	let nameSearch = $state('');
	let selectedOrg = $state<Organization | null>(null);

	let projects: SudoProject[] = $state([]);
	let orgsMap: Map<number, Organization> = $state(new Map());
	let loading = $state(true);
	let loadingMore = $state(false);
	let hasMore = $state(true);
	let error: string | null = $state(null);

	function load(more = false) {
		if (more) {
			loadingMore = true;
		} else {
			loading = true;
		}

		const beforeId = more && projects.length > 0 ? projects[projects.length - 1].id : null;

		getProjects(nameSearch || null, PER_PAGE, beforeId, selectedOrg?.id ?? null)
			.then((res) => {
				projects = more ? [...projects, ...res.projects] : res.projects;

				const newMap = more ? new Map(orgsMap) : new Map<number, Organization>();
				for (const org of res.orgs) {
					newMap.set(org.id, org);
				}
				orgsMap = newMap;

				hasMore = res.projects.length === PER_PAGE;
				error = null;
			})
			.catch((e) => {
				error = e.message;
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	// load() reads nameSearch + selectedOrg, so this re-runs (from the first page)
	// whenever the applied filters change.
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
		<div class="filters">
			<TextInput
				bind:value={nameInput}
				placeholder="Search by name"
				style="width:240px"
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
				<span class="press-enter">⏎</span>
			{/if}

			<OrganizationSelectDropdown bind:value={selectedOrg} />
		</div>
	</div>

	{#if loading}
		<Loader full />
	{:else if error}
		<IconMessage error message={error} />
	{:else if projects.length === 0}
		<IconMessage empty message="No projects found" />
	{:else}
		<div class="list">
			<div class="header">
				<div>Name</div>
				<div>ID</div>
				<div>Organization</div>
				<div>Type</div>
				<div>Created</div>
			</div>

			{#each projects as project (project.id)}
				<ProjectRow
					{project}
					org={project.organization_id !== null
						? (orgsMap.get(project.organization_id) ?? null)
						: null}
				/>
			{/each}

			<LoadButton
				text="Load More"
				loading={loadingMore}
				show={hasMore}
				on:click={() => load(true)}
			/>
		</div>
	{/if}
</SingleBox>

<style>
	.top {
		display: flex;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}
	.filters {
		flex: 1;
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		align-items: center;
	}
	.press-enter {
		color: var(--text-light);
		font-size: 14px;
		margin-left: 4px;
	}

	.list {
		flex: 1;
		overflow: auto;
		padding: 20px 0;
	}
	.header {
		display: grid;
		grid-template-columns: 3fr 1fr 2fr 1.5fr 1.5fr;
		font-size: 14px;
		font-weight: 600;
		color: var(--text-light);
		gap: 15px;
		padding: 5px 30px 15px;
	}
</style>
