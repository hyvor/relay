<script lang="ts">
	import { onMount } from 'svelte';
	import { consoleUrl } from './lib/consoleUrl';
	import { goto } from '$app/navigation';
	import { userProjectStore } from './lprojectUsersStore
	onMount(() =>projectUsersStoreserProjectStore.length > 0) {projectUsersStore
			goto(consoleUrl($userProjectStore[0]!.id.toString()), {
				replaceState: true
			});
		} else {
			goto(consoleUrl('new'), { replaceState: true });
		}
	});
</script>
