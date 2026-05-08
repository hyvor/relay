<script lang="ts">
	import { IconMessage, Loader, LoadButton, Table, TableRow, toast } from '@hyvor/design/components';
	import { getSoftDeletedProjects } from '../sudoActions';
	import { type Project, SOFT_DELETE_TTL_DAYS } from '../sudoTypes';
	import SingleBox from '../SingleBox.svelte';
	import ProjectRow from './ProjectRow.svelte';

	let projects: Project[] = $state([]);
	let offset = 0;
	let loading = $state(false);
	let initialLoad = $state(true);
	let hasMore = $state(true);
	const limit = 20;

	function loadProjects(more = false) {
		if (loading) return;

		loading = true;
		const currentOffset = more ? offset : 0;

		getSoftDeletedProjects(limit, currentOffset)
			.then((data) => {
				if (more) {
					projects = [...projects, ...data];
				} else {
					projects = data;
				}
				offset = currentOffset + data.length;
				hasMore = data.length === limit;
			})
			.catch((error) => {
				toast.error('Failed to load projects: ' + error.message);
			})
			.finally(() => {
				loading = false;
				initialLoad = false;
			});
	}

	function handleUndeleted(id: number) {
		projects = projects.filter((p) => p.id !== id);
	}

	$effect(() => {
		loadProjects(false);
	});
</script>

<SingleBox>
	{#if initialLoad && loading}
		<Loader full />
	{:else}
		<div class="header">
			<div class="header-content">
				<div class="title">Soft-Deleted Projects</div>
				<div class="tip">
					Projects are permanently deleted {SOFT_DELETE_TTL_DAYS} days after soft deletion.
				</div>
			</div>
		</div>

		{#if projects.length === 0}
			<IconMessage empty message="No soft-deleted projects" padding={150} />
		{:else}
			<div class="rows">
				<Table columns="80px 2fr 1fr 1fr 1fr 120px">
					<TableRow head>
						<div>ID</div>
						<div>Name</div>
						<div>Organization</div>
						<div>Deleted</div>
						<div>Hard delete</div>
						<div></div>
					</TableRow>
					{#each projects as project (project.id)}
						<ProjectRow {project} onUndeleted={handleUndeleted} />
					{/each}
				</Table>
			</div>

			{#if hasMore}
				<div class="load-more">
					<LoadButton
						text="Load More"
						{loading}
						show={hasMore}
						on:click={() => loadProjects(true)}
					/>
				</div>
			{/if}
		{/if}
	{/if}
</SingleBox>

<style>
	.header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px;
		border-bottom: 1px solid var(--border);
	}

	.header-content {
		display: flex;
		flex-direction: column;
		gap: 4px;
		flex: 1;
	}

	.title {
		font-size: 18px;
		font-weight: bold;
	}

	.tip {
		font-size: 14px;
		color: var(--text-light);
	}

	.rows {
		padding: 20px;
	}

	.load-more {
		margin: 20px 0;
		text-align: center;
	}
</style>
