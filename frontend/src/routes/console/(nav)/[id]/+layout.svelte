<script lang="ts">
	import { Loader, toast } from '@hyvor/design/components';
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { loadProject } from '../../lib/projectLoader';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	let isLoading = $state(true);

	onMount(() => {
		const projectId = page.params.id ?? '';
		loadProject(projectId)
			.then(() => {
				isLoading = false;
			})
			.catch(() => {
				toast.error('Unable to load Project');
			});
	});
</script>


{#if isLoading}
	<div class="full-loader">
		<Loader size="large" />
	</div>
{:else}
	{@render children?.()}
{/if}

<style>
	.full-loader {
		width: 100%;
		height: calc(100vh - var(--hyvor-bar-height));
		display: flex;
		justify-content: center;
		align-items: center;
	}
</style>