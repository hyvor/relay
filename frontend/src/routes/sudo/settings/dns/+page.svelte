<script>
	import { Button, Loader, SplitControl, Table, TableRow, toast } from '@hyvor/design/components';
	import { dnsRecordsStore, defaultDnsRecordsStore, instanceStore } from '../../sudoStore';
	import { onMount } from 'svelte';
	import { getDnsRecords, getDefaultDnsRecords } from '../../sudoActions';
	import DnsRecord from './DnsRecord.svelte';
	import DefaultDnsRecord from './DefaultDnsRecord.svelte';
	import CreateUpdateDnsRecordModal from './CreateUpdateDnsRecordModal.svelte';

	let loading = $state(false);
	let creating = $state(false);

	onMount(() => {
		Promise.all([
			getDnsRecords(),
			getDefaultDnsRecords()
		])
			.then(([customRecords, defaultRecords]) => {
				dnsRecordsStore.set(customRecords);
				defaultDnsRecordsStore.set(defaultRecords);
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
			caption="Add custom DNS records for your instance domain ({$instanceStore.domain}) and its subdomains. Served from the in-built DNS server."
		>
			{#snippet nested()}
				<div class="create">
					<Button onclick={() => (creating = true)}>Add Record</Button>
				</div>

				<div class="records">
					<Table columns="1fr 2fr 2fr 1fr 70px">
						<TableRow head>
							<div>Type</div>
							<div>Host</div>
							<div>Content</div>
							<div>TTL</div>
							<div></div>
						</TableRow>
						{#each $dnsRecordsStore as record (record.id)}
							<DnsRecord {record} />
						{/each}
					</Table>
				</div>
			{/snippet}
		</SplitControl>

		<SplitControl
			label="Default DNS Records"
			caption="System-managed DNS records required for proper email delivery. These records are automatically configured and cannot be modified."
		>
			{#snippet nested()}
				<div class="records">
					<Table columns="1fr 2fr 2fr 1fr 70px">
						<TableRow head>
							<div>Type</div>
							<div>Host</div>
							<div>Content</div>
							<div>TTL</div>
							<div></div>
						</TableRow>
						{#each $defaultDnsRecordsStore as record (record.host + record.type + record.content)}
							<DefaultDnsRecord {record} />
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
