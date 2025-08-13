<script lang="ts">
	import type { Snippet } from 'svelte';
	import { cant, SCOPE_MASK_MESSAGES } from '../../lib/scope.svelte';
	import type { Scope } from '../../types';

	interface Props {
		scope: Scope;
		children: Snippet;
	}

	let { scope, children }: Props = $props();

	let cantScope = $derived(cant(scope));
</script>

<div class="wrap" class:cant={cantScope}>
	{@render children()}

	{#if cantScope}
		<div class="notice">
			{SCOPE_MASK_MESSAGES[scope] || `You do not have access to make changes in this area.`} Please
			contact the project owner.
		</div>
	{/if}
</div>

<style>
	.wrap.cant {
		position: relative;
		pointer-events: none;
		opacity: 0.7;
	}

	.notice {
		padding: 25px 40px;
		text-align: center;
		font-size: 14px;
		border-top: 1px solid var(--border);
	}
</style>
