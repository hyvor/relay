<script lang="ts">
	import { Button, IconButton, Tag } from '@hyvor/design/components';
	import IconTrash from '@hyvor/icons/IconTrash';
	import IconArrowClockwise from '@hyvor/icons/IconArrowClockwise';
	import IconEye from '@hyvor/icons/IconEye';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import DnsRecordModal from './DnsRecordModal.svelte';
	import type { Domain } from '../../types';

	interface Props {
		domain: Domain;
		onDelete: (domain: Domain) => void;
		onVerify: (domain: Domain) => void;
	}

	let { domain, onDelete, onVerify }: Props = $props();
	let showDnsModal = $state(false);
</script>

<div class="domain-item">
	<div class="domain-info">
		<div class="domain-header">
			<span class="domain-name">{domain.domain}</span>
			<div class="domain-badges">
				<Tag size="small" color={domain.dkim_verified ? 'green' : 'orange'}>
					{domain.dkim_verified ? 'DKIM Verified' : 'DKIM Not Verified'}
				</Tag>
			</div>
		</div>
		<div class="domain-meta">
			<span>Created <RelativeTime unix={domain.created_at} /></span>
			{#if !domain.dkim_verified && domain.dkim_checked_at}
				<span>Last Check: <RelativeTime unix={domain.dkim_checked_at} /></span>
			{/if}
			{#if !domain.dkim_verified && domain.dkim_error_message}
				<span>Error: {domain.dkim_error_message}</span>
			{/if}
		</div>
	</div>
	<div class="domain-actions">
		<Button
			color="input"
			size="small"
			on:click={() => showDnsModal = true}
		>
			{#snippet start()}
				<IconEye size={12} />
			{/snippet}
			DNS Record
		</Button>
		{#if !domain.dkim_verified}
			<Button
				color="input"
				size="small"
				on:click={() => onVerify(domain)}
			>
				{#snippet start()}
					<IconArrowClockwise size={12} />
				{/snippet}
				Verify
			</Button>
		{/if}
		<IconButton
			variant="fill-light"
			color="red"
			size="small"
			on:click={() => onDelete(domain)}
		>
				<IconTrash size={12} />
		</IconButton>
	</div>
</div>

<DnsRecordModal {domain} bind:show={showDnsModal} />

<style>
	.domain-item {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		padding: 4px;
		border-radius: 8px;
		background-color: var(--bg-light);
	}

	.domain-info {
		flex: 1;
	}

	.domain-header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 8px;
	}

	.domain-name {
		font-weight: 600;
		font-size: 16px;
	}

	.domain-badges {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	.domain-meta {
		display: flex;
		gap: 16px;
		font-size: 14px;
		color: var(--text-light);
		margin-bottom: 12px;
	}

	.domain-actions {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-left: 20px;
	}
</style> 