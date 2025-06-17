<script lang="ts">
	import { onMount } from 'svelte';
	import { consoleUrl } from './lib/consoleUrl';
	import { goto } from '$app/navigation';
	import { userProjectStore } from './lib/stores/userProjectStore';

	onMount(() => {
		if ($userProjectStore.length > 0) {
			goto(consoleUrl($userProjectStore[0]!.id.toString()), {
				replaceState: true
			});
		} else {
			goto(consoleUrl('new'), { replaceState: true });
		}
	});
</script>
