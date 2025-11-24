<script lang="ts">
	import './app.css';
	import { Base } from '@hyvor/design/components';
	import { onMount } from 'svelte';
	import { track } from '@hyvor/design/marketing';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	onMount(() => {
		track.init();
	});
</script>

<Base>
	{@render children?.()}
</Base>
