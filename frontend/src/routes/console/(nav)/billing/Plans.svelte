<script lang="ts">
	import { onMount } from 'svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	let wrap: HTMLDivElement | undefined = $state();

	onMount(() => {
		if (!wrap) {
			return;
		}

		const script = document.createElement('script');
		script.src = getAppConfig().hyvor.instance + '/js/billing-component-iframe.js?component=relay';
		wrap.appendChild(script);

		return () => {
			wrap?.removeChild(script);
		};
	});
</script>

<div class="content" bind:this={wrap}></div>
