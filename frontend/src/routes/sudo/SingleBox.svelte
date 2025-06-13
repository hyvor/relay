<script lang="ts">
	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<div class="single-box hds-box">
	{@render children?.()}
</div>

<style>
	.single-box {
		min-height: 100%;
		display: flex;
		flex-direction: column;
	}
</style>
