<script lang="ts">
	import { userProjectStore } from "../../lib/stores/userProjectStore";
	import ProjectRow from "./ProjectRow.svelte";

	const projects = $userProjectStore;
</script>

{#if projects.length}
	<div class="wrap">
		<div class="title-wrap">
			<div class="title">
				Projects
			</div>
		</div>
		{#each projects as project}
			<ProjectRow project={project} />
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
