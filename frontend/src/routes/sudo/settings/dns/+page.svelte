<script>
	import { Button, Loader, SplitControl, toast } from '@hyvor/design/components';
	import { dnsRecordsStore, instanceStore } from '../../sudoStore';
	import { onMount } from 'svelte';
	import { getDnsRecords } from '../../sudoActions';

	let loading = $state(false);

	onMount(() => {
		getDnsRecords()
			.then((response) => {
				dnsRecordsStore.set(response);
			})
			.catch((error) => {
				toast.error('Failed to fetch DNS records:', error);
			})
			.finally(() => {
				loading = false;
			});
	});
</script>

<div class="dns">
	{#if loading}
		<Loader size="large" />
	{:else}
		<SplitControl
			label="Custom DNS Records"
			caption="Add custom DNS records for your instance domain {$instanceStore.domain} and its subdomains. Served from the in-built DNS server."
		>
			{#snippet nested()}
				<div class="create">
					<Button>Add Record</Button>
				</div>

				<div class="records">
					{#each $dnsRecordsStore as record (record.id)}
						{record.type} {record.subdomain} {record.content}
					{/each}
				</div>
			{/snippet}
		</SplitControl>
	{/if}
</div>

<style>
	.dns {
		padding: 30px 40px;
	}
</style>
