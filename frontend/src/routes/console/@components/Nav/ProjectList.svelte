<script>
	import { getProjectUsers } from '../../lib/stores/projectStore.svelte';
	import ProjectRow from './ProjectRow.svelte';

	let projectUsers = getProjectUsers();
</script>

{#if projectUsers.length}
	<div class="wrap">
		<div class="title-wrap">
			<div class="title">Projects</div>
		</div>
		{#each projectUsers as pu}
			<ProjectRow project={pu.project} />
		{/each}
	</div>
{/if}

<style>
	.wrap {
		margin-bottom: 35px;
	}
	.title-wrap {
		margin-bottom: 15px;
	}
	.title {
		font-weight: 600;
	}
</style>
