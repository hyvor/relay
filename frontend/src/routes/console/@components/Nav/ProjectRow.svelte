<script lang="ts">
	import { goto } from '$app/navigation';
	import { Tag } from '@hyvor/design/components';
	import { projectStore } from '../../lib/stores/projectStore';
	import type { Project } from '../../types';
	import { selectingProject } from '../../lib/stores/consoleStore';

	export let project: Project;

	function onClick() {
		projectStore.set(project);
		goto(`/console/${project.id}`);
		//loadNewsletter(String(newsletterList.newsletter.id));
		selectingProject.set(false);
	}
</script>

<div
	class="wrap"
	role="button"
	on:click={onClick}
	on:keyup={(e) => e.key === 'Enter' && onClick()}
	tabindex="0"
>
	<div class="name-id">
		<div class="name">{project.name}</div>
		<div class="id">
			<span class="id-tag">ID: </span><Tag size="x-small"
				><strong>{project.id}</strong></Tag
			>
		</div>
	</div>

	<div class="right">&rarr;</div>
</div>

<style lang="scss">
	.wrap {
		padding: 15px 25px;
		background-color: var(--accent-light-mid);
		cursor: pointer;
		border-radius: var(--box-radius);
		display: flex;
		align-items: center;
		position: relative;
		overflow: hidden;
		margin-bottom: 10px;
	}
	.name-id {
		flex: 2;
	}
	.name {
		font-weight: 600;
	}
	.id-tag {
		font-size: 12px;
		color: var(--text-light);
		margin-right: 5px;
	}
	.role {
		margin-right: 15px;
	}

	@media (max-width: 768px) {
		.wrap {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			grid-template-rows: repeat(3, min-content);
			grid-row-gap: 10px;
		}
		.right {
			grid-area: 1 / 5 / 4 / 6;
			text-align: center;
		}
		.name-id {
			grid-area: 1 / 1 / 1 / 5;
		}
		.role {
			grid-area: 3 / 1 / 3 / 2;
		}
	}
</style>
