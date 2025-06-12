<script lang="ts">
	import {
		HyvorBar,
		InternationalizationProvider,
		DarkProvider,
		Loader,
		toast
	} from '@hyvor/design/components';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	let isLoading = $state(false);
</script>

<svelte:head>
	<title>Console · Hyvor Post</title>
	<meta name="robots" content="noindex" />
</svelte:head>


<main>
	{#if isLoading}
		<div class="full-loader">
			<Loader size="large"></Loader>
		</div>
	{:else}
		<HyvorBar product="core" instance="https://hyvor.dev" config={{ name: 'Hyvor Relay' }} />
		{@render children?.()}
	{/if}
</main>


<style>
	main {
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100vh;
	}

	.full-loader {
		width: 100%;
		height: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
	}
</style>
