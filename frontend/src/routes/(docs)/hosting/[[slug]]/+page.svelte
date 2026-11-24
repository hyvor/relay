<script lang="ts">
	import { Docs } from '@hyvor/design/marketing';

	let { data } = $props();
</script>

<svelte:head>
	<title>{data.page.name} - Hyvor Relay Hosting</title>
	{#if data.page.description}
		<meta name="description" content={data.page.description} />
	{/if}
</svelte:head>

<div class="docs">
	<Docs {...data} />
</div>

<style>
	.docs {
		margin-bottom: 80px;
	}
</style>
