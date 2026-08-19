<script lang="ts">
	import { Docs } from '@hyvor/design/marketing';

	let { data } = $props();
</script>

<svelte:head>
	<title>
		{data.page.name} | Hyvor Relay
	</title>
	<link
		rel="canonical"
		href="https://relay.hyvor.com/hosting{data.slug ? '/' + data.slug : ''}"
	/>
</svelte:head>

<Docs {...data} />
