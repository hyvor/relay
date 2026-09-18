<script lang="ts">
	import { Button } from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconCaretUp from '@hyvor/icons/IconCaretUp';
	import RelativeTime from '../../console/@components/content/RelativeTime.svelte';
	import KycStatusTag from './KycStatusTag.svelte';
	import KycActionModal from './KycActionModal.svelte';
	import type { Organization, SudoKyc } from '../sudoTypes';

	interface Props {
		kyc: SudoKyc;
		org: Organization | null;
		onUpdate: (kyc: SudoKyc) => void;
	}

	let { kyc, org, onUpdate }: Props = $props();

	let opened = $state(false);
	let modalOpen = $state(false);
	let modalAction = $state<'approve' | 'reject'>('approve');

	const orgName = $derived(org?.name ?? 'organization #' + kyc.organization_id);

	function openModal(action: 'approve' | 'reject', e: Event) {
		e.stopPropagation();
		modalAction = action;
		modalOpen = true;
	}
</script>

<div class="wrap">
	<button class="row" onclick={() => (opened = !opened)} class:opened>
		<div class="org">
			{#if org}
				{org.name}
			{:else}
				<span class="muted">org #{kyc.organization_id}</span>
			{/if}
		</div>
		<div class="applicant">
			<div class="name">{kyc.name}</div>
		</div>
		<div>{kyc.country}</div>
		<div><KycStatusTag status={kyc.status} /></div>
		<div class="date"><RelativeTime unix={kyc.created_at} /></div>
		<div class="actions">
			{#if kyc.status === 'pending'}
				<Button size="x-small" color="green" on:click={(e) => openModal('approve', e)}>
					Approve
				</Button>
				<Button
					size="x-small"
					color="red"
					variant="outline"
					on:click={(e) => openModal('reject', e)}
				>
					Reject
				</Button>
			{/if}
			<span class="caret">
				{#if opened}
					<IconCaretUp size={12} />
				{:else}
					<IconCaretDown size={12} />
				{/if}
			</span>
		</div>
	</button>

	{#if opened}
		<div class="details">
			<div class="detail">
				<span class="label">Account type</span>
				<span class="value">{kyc.account_type}</span>
			</div>
			<div class="detail">
				<span class="label">Content ownership</span>
				<span class="value">{kyc.content_ownership.replace('_', ' ')}</span>
			</div>
			<div class="detail">
				<span class="label">Sending type</span>
				<span class="value">{kyc.sending_type.join(', ')}</span>
			</div>
			<div class="detail">
				<span class="label">Address</span>
				<span>{kyc.address}</span>
			</div>
			<div class="detail">
				<span class="label">Website</span>
				<a href={kyc.website} target="_blank" rel="noreferrer">{kyc.website}</a>
			</div>
			<div class="detail">
				<span class="label">Email</span>
				<span>{kyc.email}</span>
			</div>
			<div class="detail full">
				<span class="label">Use case</span>
				<span>{kyc.use_case}</span>
			</div>
			{#if kyc.reject_reason}
				<div class="detail full">
					<span class="label">Rejection reason</span>
					<span>{kyc.reject_reason}</span>
				</div>
			{/if}
			{#if kyc.note}
				<div class="detail full">
					<span class="label">Private note</span>
					<span>{kyc.note}</span>
				</div>
			{/if}
		</div>
	{/if}

	<KycActionModal kyc={kyc} action={modalAction} {orgName} bind:show={modalOpen} onDone={onUpdate} />
</div>

<style>
	.wrap {
		border-bottom: 1px solid var(--border);
	}
	.row {
		display: grid;
		grid-template-columns: 1.5fr 2fr 1fr 1fr 1fr 2fr;
		gap: 15px;
		align-items: center;
		padding: 12px 30px;
		text-align: left;
		width: 100%;
		font-size: 14px;
	}
	.row:hover,
	.row.opened {
		background: var(--hover);
	}
	.applicant .name {
		font-weight: 600;
	}
	.muted {
		color: var(--text-light);
	}
	.date {
		font-size: 13px;
		color: var(--text-light);
	}
	.actions {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 8px;
	}
	.caret {
		color: var(--text-light);
		display: flex;
	}
	.details {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 10px 30px;
		padding: 0 30px 15px;
		font-size: 13px;
	}
	.detail .label {
		display: block;
		color: var(--text-light);
		font-size: 12px;
		margin-bottom: 2px;
	}
	.detail .value {
		text-transform: capitalize;
	}
	.detail.full {
		grid-column: 1 / -1;
	}
</style>
