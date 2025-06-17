<script lang="ts">
	import { Loader } from '@hyvor/design/components';
	import Nav from '../@components/nav/Nav.svelte';
	import ProjectSelector from '../@components/Nav/ProjectSelector.svelte';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	let isLoading = $state(false);
</script>

<div class="main-inner">
	{#if isLoading}
		<div class="full-loader">
			<Loader size="large" />
		</div>
	{:else}
		<Nav />
		<ProjectSelector />
		<div class="content">
			{@render children?.()}
		</div>
	{/if}
</div>

<style>
	.main-inner {
		display: flex;
		flex: 1;
		width: 100%;
		height: 100%;
		min-height: 0;
	}

	.content {
		display: flex;
		flex-direction: column;
		padding: 15px;
		flex: 1;
		width: 100%;
		height: 100%;
		min-width: 0;
	}

	.full-loader {
		width: 100%;
		height: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
	}

	@media (max-width: 992px) {
		.main-inner {
			display: block;
		}
		.content {
			padding-bottom: 150px;
			height: initial;
			min-height: calc(100vh - 50px);
		}
	}
</style>
