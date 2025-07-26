<script>
	import { Button, Loader, SplitControl, Table, TableRow, toast } from '@hyvor/design/components';
	import { dnsRecordsStore, instanceStore } from '../../sudoStore';
	import { onMount } from 'svelte';
	import { getDnsRecords } from '../../sudoActions';
	import DnsRecord from './DnsRecord.svelte';
	import CreateUpdateDnsRecordModal from './CreateUpdateDnsRecordModal.svelte';

	let loading = $state(false);
	let creating = $state(false);

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
					<Button onclick={() => (creating = true)}>Add Record</Button>
				</div>

				<div class="records">
					<Table columns="1fr 2fr 2fr 1fr 100px">
						<TableRow head>
							<div>Type</div>
							<div>Host</div>
							<div>Content</div>
							<div>TTL</div>
						</TableRow>
						{#each $dnsRecordsStore as record (record.id)}
							<DnsRecord {record} />
						{/each}
					</Table>
				</div>
			{/snippet}
		</SplitControl>
	{/if}
</div>

{#if creating}
	<CreateUpdateDnsRecordModal bind:show={creating} />
{/if}

<style>
	.dns {
		padding: 30px 40px;
	}
	.create {
		margin-bottom: 20px;
	}
</style>
