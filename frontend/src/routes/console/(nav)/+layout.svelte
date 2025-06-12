<script lang="ts">
	import Nav from '../@components/Nav/Nav.svelte';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<div class="main-inner">
	<Nav />
	<div class="content">
		{@render children?.()}
	</div>
</div>

<style>
	.main-inner {
		display: flex;
		flex: 1;
		width: 100%;
		height: 100%;
		min-height: 0;
	}

	.content {
		display: flex;
		flex-direction: column;
		padding: 15px;
		flex: 1;
		width: 100%;
		height: 100%;
		min-width: 0;
	}

	@media (max-width: 992px) {
		.main-inner {
			display: block;
		}
		.content {
			padding-bottom: 150px;
			height: initial;
			min-height: calc(100vh - 50px);
		}
	}
</style>
