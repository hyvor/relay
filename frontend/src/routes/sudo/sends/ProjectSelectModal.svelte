<script lang="ts">
	import { Modal, TextInput, LoadButton, Loader, toast } from '@hyvor/design/components';
	import { getProjects } from '../sudoActions';
	import type { SudoProject } from '../sudoTypes';

	interface Props {
		show: boolean;
		onClose: () => void;
		onSelect: (project: SudoProject | null) => void;
	}

	let { show = $bindable(), onClose, onSelect }: Props = $props();

	const PER_PAGE = 25;
	const DEBOUNCE_MS = 300;

	let searchInput = $state('');
	let search = $state('');
	let projects: SudoProject[] = $state([]);
	let loading = $state(false);
	let loadingMore = $state(false);
	let hasMore = $state(false);
	let debounceTimer: ReturnType<typeof setTimeout> | null = $state(null);

	function load(more = false) {
		const trimmed = search.trim();
		const searchParam = trimmed === '' ? null : trimmed;

		more ? (loadingMore = true) : (loading = true);

		getProjects(
			searchParam,
			PER_PAGE,
			more && projects.length > 0 ? projects[projects.length - 1].id : null
		)
			.then((data) => {
				projects = more ? [...projects, ...data] : data;
				hasMore = data.length === PER_PAGE;
			})
			.catch((err) => {
				toast.error('Failed to load projects: ' + err.message);
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	$effect(() => {
		if (!show) return;
		search;
		load(false);
	});

	function handleSearchInput() {
		if (debounceTimer) clearTimeout(debounceTimer);
		debounceTimer = setTimeout(() => {
			search = searchInput;
		}, DEBOUNCE_MS);
	}

	function handleClose() {
		show = false;
		searchInput = '';
		search = '';
		onClose();
	}

	function handleSelect(project: SudoProject | null) {
		onSelect(project);
		handleClose();
	}
</script>

<Modal
	bind:show
	size="medium"
	title="Filter by project"
	footer={{ cancel: { text: 'Cancel' }, confirm: false }}
	on:cancel={handleClose}
>
	<div class="modal-content">
		<div class="search-section">
			<TextInput
				bind:value={searchInput}
				placeholder="Search projects..."
				block
				on:input={handleSearchInput}
			/>
		</div>

		<div class="actions">
			<button class="project-item all" onclick={() => handleSelect(null)}>
				<div class="project-name">All projects</div>
				<div class="project-meta">No filter</div>
			</button>
		</div>

		<div class="results">
			{#if loading}
				<Loader />
			{:else if projects.length === 0}
				<div class="no-results">
					{search.trim() !== ''
						? 'No projects match your search.'
						: 'No projects available.'}
				</div>
			{:else}
				<div class="project-list">
					{#each projects as project (project.id)}
						<button class="project-item" onclick={() => handleSelect(project)}>
							<div class="project-name">{project.name}</div>
							<div class="project-meta">
								#{project.id}
								{#if project.organization_id !== null}
									· org {project.organization_id}
								{/if}
								· {project.send_type}
							</div>
						</button>
					{/each}
				</div>

				<LoadButton
					text="Load More"
					loading={loadingMore}
					show={hasMore}
					on:click={() => load(true)}
				/>
			{/if}
		</div>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 10px 0;
	}
	.search-section {
		margin-bottom: 15px;
	}
	.actions {
		margin-bottom: 10px;
	}
	.project-item {
		display: block;
		width: 100%;
		text-align: left;
		padding: 10px 12px;
		border-radius: 8px;
		background: transparent;
		border: none;
		cursor: pointer;
	}
	.project-item:hover {
		background: var(--hover);
	}
	.project-item.all {
		border: 1px dashed var(--border);
	}
	.project-name {
		font-weight: 600;
	}
	.project-meta {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 2px;
	}
	.project-list {
		max-height: 320px;
		overflow-y: auto;
	}
	.no-results {
		text-align: center;
		color: var(--text-light);
		padding: 30px 20px;
		font-style: italic;
	}
</style>
