<script lang="ts">
	import { Footer, Header } from '@hyvor/design/marketing';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<Header />

{@render children?.()}

<Footer />
