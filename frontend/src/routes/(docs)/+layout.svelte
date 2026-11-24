<script lang="ts">
	import { page } from '$app/state';

	// docs are rendered at hyvor.com/relay (the canonical location), synced from ../docs
	// they are only rendered in this app in dev (see the Caddyfile redirects)
	const CANONICAL_BASE = 'https://hyvor.com/relay';

	let { children } = $props();
</script>

<svelte:head>
	<link rel="canonical" href="{CANONICAL_BASE}{page.url.pathname}" />
</svelte:head>

{@render children?.()}
