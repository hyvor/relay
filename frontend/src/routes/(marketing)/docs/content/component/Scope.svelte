<script lang="ts">
	import { Tag, Text } from '@hyvor/design/components';
	import type { Snippet } from 'svelte';

	interface Props {
		send: boolean;
		domain: boolean;
		admin: boolean;
	}

	let { children }: Props = $props();
</script>

<Tag size="small" color="blue">{@render children()}</Tag>
