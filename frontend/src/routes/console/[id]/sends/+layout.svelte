<script lang="ts">
	import { onMount, type Snippet } from 'svelte';
	import { redirectIfCant } from '../../lib/scope.svelte';

	interface Props {
		children: Snippet;
	}

	let { children }: Props = $props();

	onMount(() => redirectIfCant('sends.read'));
</script>

{@render children()}
