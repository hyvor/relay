<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { Snippet } from 'svelte';

	interface Props {
		children: Snippet;
	}

	let { children }: Props = $props();
</script>

<span>
	<Tag size="small" color="blue">{@render children()}</Tag>
</span>

<style>
	span {
		line-height: 16px;
	}
</style>
