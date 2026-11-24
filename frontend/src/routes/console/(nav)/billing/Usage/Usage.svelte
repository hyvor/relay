<script lang="ts">
	import { onMount } from 'svelte';
	import UsageBar from './UsageBar.svelte';
	import { getUsage, type UsageData } from '../billingActions';
	import { Loader, toast } from '@hyvor/design/components';

	let usage: UsageData | undefined = $state();
	let loading = $state(true);

	onMount(() => {
		getUsage()
			.then((data) => {
				usage = data;
			})
			.catch((error) => {
				toast.error(error.message);
			})
			.finally(() => {
				loading = false;
			});
	});
</script>

{#if loading}
	<Loader padding={100} block />
{:else if usage}
	<div class="usage">
		<UsageBar name="Emails (this month)" data={usage.emails} />
		<UsageBar name="Projects" data={usage.projects} />
		<UsageBar name="Domains" data={usage.domains} />
	</div>
{/if}
