<script lang="ts">
	import { Button, IconButton } from '@hyvor/design/components';
	import IconTrash from '@hyvor/icons/IconTrash';
	import IconArrowClockwise from '@hyvor/icons/IconArrowClockwise';
	import IconEye from '@hyvor/icons/IconEye';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import DnsRecordModal from './DnsRecordModal.svelte';
	import type { Domain } from '../../types';
	import { cant } from '../../lib/scope.svelte';
	import DomainStatusTag from './DomainStatusTag.svelte';

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
				<DomainStatusTag status={domain.status} />
			</div>
		</div>
		<div class="domain-meta">
			<div class="timestamp">
				<span>Created <RelativeTime unix={domain.created_at} /></span>
				{#if (domain.status === 'pending' || domain.status === 'warning') && domain.dkim_checked_at}
					<span>Last Checked: <RelativeTime unix={domain.dkim_checked_at} /></span>
				{/if}
			</div>
			{#if (domain.status === 'pending' || domain.status === 'warning') && domain.dkim_error_message}
				<div class="error-message">
					<span>Error: {domain.dkim_error_message}</span>
				</div>
			{/if}
		</div>
	</div>
	<div class="domain-actions">
		<Button color="input" size="small" on:click={() => (showDnsModal = true)}>
			{#snippet start()}
				<IconEye size={12} />
			{/snippet}
			DNS Record
		</Button>
		{#if domain.status === 'pending' || domain.status === 'warning'}
			<Button
				color="input"
				size="small"
				on:click={() => onVerify(domain)}
				disabled={cant('domains.write')}
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
			disabled={cant('domains.write') || domain.status === 'suspended'}
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
		border-radius: 8px;
		background-color: var(--bg-light);
		word-break: break-all;
		padding: 15px 30px;
	}

	.domain-info {
		flex: 1;
	}

	.domain-header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 3px;
	}

	.domain-name {
		font-weight: 600;
		font-size: 16px;
	}

	.domain-badges {
		display: flex;
		gap: 8px;
		align-items: center;
		text-transform: capitalize;
	}

	.domain-meta {
		font-size: 14px;
		color: var(--text-light);
	}

	.timestamp {
		display: flex;
		gap: 10px;
	}

	.domain-actions {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-left: 20px;
	}
</style>
