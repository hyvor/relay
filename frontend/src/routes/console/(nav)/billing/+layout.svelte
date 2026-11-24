<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { get } from 'svelte/store';
	import { getAppConfig, kycStatusStore, authOrganizationStore } from '../../lib/stores/consoleStore';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	onMount(() => {
		const role = get(authOrganizationStore)?.role;
		const canAccess = role === 'admin' || role === 'billing';

		if (getAppConfig().deployment !== 'cloud' || get(kycStatusStore) !== 'approved' || !canAccess) {
			goto('/console');
		}
	});
</script>

{@render children?.()}
