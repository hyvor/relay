<script lang="ts">
	import { confirm, IconButton, TableRow, Tag, toast } from '@hyvor/design/components';
	import type { DnsRecord } from '../../sudoTypes';
	import { getHost } from './dns';
	import IconPencil from '@hyvor/icons/IconPencil';
	import IconTrash from '@hyvor/icons/IconTrash';
	import { deleteDnsRecord } from '../../sudoActions';
	import { dnsRecordsStore } from '../../sudoStore';
	import CreateUpdateDnsRecordModal from './CreateUpdateDnsRecordModal.svelte';

	interface Props {
		record: DnsRecord;
	}

	let { record }: Props = $props();
	let updating = $state(false);
	let expanded = $state(false);

	function toggleExpanded() {
		expanded = !expanded;
	}

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			toggleExpanded();
		}
	}

	function truncateText(text: string, maxLength: number = 50): string {
		if (text.length <= maxLength) return text;
		return text.substring(0, maxLength) + '...';
	}

	async function handleDelete() {
		const confirmed = await confirm({
			title: 'Delete DNS Record',
			content: `Are you sure you want to delete the DNS record for ${getHost(record.subdomain)}? This action cannot be undone.`,
			confirmText: 'Delete',
			cancelText: 'Cancel',
			danger: true,
			autoClose: false
		});

		if (!confirmed) return;

		confirmed.loading();

		deleteDnsRecord(record.id)
			.then(() => {
				toast.success('DNS record deleted successfully');
				dnsRecordsStore.update((records) => records.filter((r) => r.id !== record.id));
			})
			.catch((error) => {
				toast.error('Failed to delete DNS record:', error);
			})
			.finally(() => {
				confirmed.close();
			});
	}
</script>

<TableRow>
	<div class="type">{record.type}</div>
	<div class="host">
		{getHost(record.subdomain)}

		{#if record.type === 'MX'}
			<Tag size="small">{record.priority}</Tag>
		{/if}
	</div>
	{#if record.content.length > 50}
		<div 
			class="content clickable" 
			onclick={toggleExpanded}
			onkeydown={handleKeydown}
			role="button"
			tabindex="0"
			aria-expanded={expanded}
			aria-label={`DNS record content: ${expanded ? 'expanded' : 'collapsed'}. Click to ${expanded ? 'collapse' : 'expand'}`}
		>
			{expanded ? record.content : truncateText(record.content)}
		</div>
	{:else}
		<div class="content">
			{record.content}
		</div>
	{/if}
	<div class="ttl">{record.ttl} seconds</div>

	<div class="actions">
		<IconButton color="input" size="small" on:click={() => (updating = true)}>
			<IconPencil size={12} />
		</IconButton>
		<IconButton color="red" variant="fill-light" size="small" on:click={handleDelete}>
			<IconTrash size={12} />
		</IconButton>
	</div>
</TableRow>

{#if updating}
	<CreateUpdateDnsRecordModal {record} bind:show={updating} />
{/if}

<style>
	.content {
		word-break: break-all;
	}
	.host {
        word-break: break-all;  
    }
	
	.content.clickable {
		cursor: pointer;
		color: var(--color-primary);
		text-decoration: underline;
		text-decoration-style: dotted;
	}
	
	.content.clickable:hover,
	.content.clickable:focus {
		color: var(--color-primary-dark);
		outline: 2px solid var(--color-primary);
		outline-offset: 2px;
	}
</style>
