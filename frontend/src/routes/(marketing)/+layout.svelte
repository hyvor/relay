<script lang="ts">
	import { page } from '$app/state';
	import Header from './Header.svelte';
	import Footer from './Footer.svelte';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<Header />
{@render children?.()}
<Footer />

<style>
	.docs {
		margin-right: 5px;
		display: flex;
		gap: 5px;
		align-items: center;
	}
</style>
