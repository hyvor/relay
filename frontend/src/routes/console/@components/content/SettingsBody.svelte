<script lang="ts">
	let { children }: { children?: import('svelte').Snippet } = $props();
</script>

<div class="settings-body">
	{@render children?.()}
</div>

<style>
	.settings-body {
		overflow: auto;
		padding: 25px 30px;
	}
</style>
