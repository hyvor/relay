<script lang="ts">
	import { Base } from '@hyvor/design/components';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<Base>
	{@render children?.()}
</Base>

<style>
	:global(:root) {
		--accent-light-mid: var(--accent-lightest);
	}
</style>
