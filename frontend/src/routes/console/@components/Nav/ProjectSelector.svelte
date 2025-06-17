<script lang="ts">
	import { Button, Modal } from '@hyvor/design/components';
	import { selectingProject } from '../../lib/stores/consoleStore';
	import IconPlus from '@hyvor/icons/IconPlus';
	import ProjectList from './ProjectList.svelte';
</script>

{#if $selectingProject}
	<Modal size="large" bind:show={$selectingProject}>
		{#snippet title()}
			<span class="title">Choose a newsletter </span>
			<Button as="a" href="/console/new" on:click={() => selectingProject.set(false)}>
				Create new newsletter
				{#snippet end()}
					<IconPlus size={12} />
				{/snippet}
			</Button>
		{/snippet}

		<div class="wrap">
			<ProjectList />
		</div>
	</Modal>
{/if}

<style>
	.wrap {
		padding: 0 15px;
	}

	.title {
		font-size: 20px;
		font-weight: 600;
		margin-right: 10px;
	}
</style>
