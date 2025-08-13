<script lang="ts">
	import { onMount } from 'svelte';
	import { getProjectUsers } from './lib/stores/projectStore.svelte';
	import { goto } from '$app/navigation';
	import { consoleUrl } from './lib/consoleUrl.svelte';

	onMount(() => {
		const projectUsers = getProjectUsers();

		if (projectUsers.length > 0) {
			goto(consoleUrl(projectUsers[0].project.id.toString()), {
				replaceState: true
			});
		} else {
			goto(consoleUrl('new'), { replaceState: true });
		}
	});
</script>
