<script lang="ts">
	import { Modal, Checkbox, TextInput } from '@hyvor/design/components';
	import type { SendRecipient } from '../../../types';

	interface Props {
		show: boolean;
		failedRecipients: SendRecipient[];
		loading: boolean;
		onConfirm: (recipientIds: number[], mode: 'now' | 'schedule', scheduledDate?: string) => void;
	}

	let { show = $bindable(), failedRecipients, loading, onConfirm }: Props = $props();

	let selectedRecipientIds = $state<number[]>([]);
	let mode = $state<'now' | 'schedule'>('now');
	let scheduledDate = $state('');

	// Reset state when modal opens
	$effect(() => {
		if (show) {
			selectedRecipientIds = failedRecipients.map((r) => r.id);
			mode = 'now';
			scheduledDate = '';
		}
	});

	function handleSelectAll() {
		selectedRecipientIds = failedRecipients.map((r) => r.id);
	}

	function handleDeselectAll() {
		selectedRecipientIds = [];
	}

	function handleRecipientToggle(recipientId: number) {
		if (selectedRecipientIds.includes(recipientId)) {
			selectedRecipientIds = selectedRecipientIds.filter((id) => id !== recipientId);
		} else {
			selectedRecipientIds = [...selectedRecipientIds, recipientId];
		}
	}

	function handleConfirm() {
		if (selectedRecipientIds.length === 0) return;
		onConfirm(selectedRecipientIds, mode, mode === 'schedule' ? scheduledDate : undefined);
	}

	const allSelected = $derived(selectedRecipientIds.length === failedRecipients.length);
	const noneSelected = $derived(selectedRecipientIds.length === 0);
</script>

<Modal
	bind:show
	{loading}
	size="medium"
	title="Retry Failed Recipients"
	footer={{
		cancel: { text: 'Cancel' },
		confirm: { text: mode === 'now' ? 'Retry Now' : 'Schedule Retry', props: { disabled: noneSelected } }
	}}
	on:confirm={handleConfirm}
>
	<div class="modal-content">
		<div class="mode-section">
			<label class="radio-label">
				<input type="radio" bind:group={mode} value="now" />
				Retry now
			</label>
			<label class="radio-label">
				<input type="radio" bind:group={mode} value="schedule" />
				Schedule retry
			</label>

			{#if mode === 'schedule'}
				<div class="schedule-input">
					<TextInput type="datetime-local" bind:value={scheduledDate} block />
				</div>
			{/if}
		</div>

		<div class="recipients-section">
			<div class="recipients-header">
				<span class="recipients-label">
					Recipients ({selectedRecipientIds.length}/{failedRecipients.length})
				</span>
				<div class="recipients-actions">
					<button
						type="button"
						class="action-btn"
						disabled={loading || allSelected}
						onclick={handleSelectAll}
					>
						Select all
					</button>
					<button
						type="button"
						class="action-btn"
						disabled={loading || noneSelected}
						onclick={handleDeselectAll}
					>
						Deselect all
					</button>
				</div>
			</div>
			<div class="recipients-list">
				{#each failedRecipients as recipient (recipient.id)}
					<div class="recipient-item">
						<Checkbox
							checked={selectedRecipientIds.includes(recipient.id)}
							disabled={loading}
							on:change={() => handleRecipientToggle(recipient.id)}
						>
							<span class="recipient-address">{recipient.address}</span>
						</Checkbox>
					</div>
				{/each}
			</div>
		</div>
	</div>
</Modal>

<style>
	.modal-content {
		max-height: 70vh;
		overflow-y: auto;
		padding: 0 2px;
	}

	.mode-section {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin-bottom: 20px;
	}

	.radio-label {
		display: flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		font-size: 14px;
	}

	.schedule-input {
		margin-top: 8px;
		margin-left: 24px;
	}

	.recipients-section {
		border-top: 1px solid var(--border);
		padding-top: 16px;
	}

	.recipients-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 12px;
	}

	.recipients-label {
		font-weight: 600;
		font-size: 14px;
	}

	.recipients-actions {
		display: flex;
		gap: 16px;
	}

	.action-btn {
		background: none;
		border: none;
		color: var(--primary);
		cursor: pointer;
		font-size: 14px;
		padding: 0;
		text-decoration: underline;
		transition: color 0.2s;
	}

	.action-btn:hover:not(:disabled) {
		color: var(--primary-dark);
	}

	.action-btn:disabled {
		color: var(--text-light);
		cursor: not-allowed;
		text-decoration: none;
	}

	.recipients-list {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.recipient-item {
		display: flex;
		align-items: center;
	}

	.recipient-address {
		font-size: 14px;
	}
</style>
