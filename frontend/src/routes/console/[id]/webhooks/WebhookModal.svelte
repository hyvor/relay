<script lang="ts">
	import { Modal, Button, TextInput, SplitControl, toast, Checkbox } from '@hyvor/design/components';
	import { createWebhook, updateWebhook } from '../../lib/actions/webhookActions';
	import { getAppConfig } from '../../lib/stores/consoleStore';
	import type { Webhook } from '../../types';

	interface Props {
		show: boolean;
		webhook?: Webhook | null;
		onWebhookSaved?: (webhook: Webhook) => void;
	}

	let { show = $bindable(), webhook = $bindable(), onWebhookSaved = () => {} }: Props = $props();

	let url = $state('');
	let description = $state('');
	let selectedEvents = $state<string[]>([]);
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});

	const availableEvents = getAppConfig().app?.webhook?.events || [];
	const isEditing = $derived(webhook !== null && webhook !== undefined);
	const modalTitle = $derived(isEditing ? 'Edit Webhook' : 'Create Webhook');
	const submitButtonText = $derived(isEditing ? 'Update Webhook' : 'Create Webhook');

	// Update form when webhook changes
	$effect(() => {
		if (webhook) {
			url = webhook.url;
			description = webhook.description;
			selectedEvents = [...webhook.events];
		} else {
			resetForm();
		}
	});

	function resetForm() {
		url = '';
		description = '';
		selectedEvents = [];
		errors = {};
	}

	function validateForm(): boolean {
		errors = {};

		if (!url.trim()) {
			errors.url = 'URL is required';
		} else {
			try {
				new URL(url.trim());
			} catch {
				errors.url = 'Please enter a valid URL';
			}
		}

		if (selectedEvents.length === 0) {
			errors.events = 'At least one event must be selected';
		}

		return Object.keys(errors).length === 0;
	}

	function handleSubmit() {

		if (!validateForm()) {
			const errorMessages = Object.values(errors);
			if (errorMessages.length > 0) {
				toast.error(errorMessages.join('. '));
			}
			return;
		}

		loading = true;
		const action = isEditing 
			? updateWebhook(webhook!.id, url.trim(), description.trim(), selectedEvents)
			: createWebhook(url.trim(), description.trim(), selectedEvents);

		action
			.then((savedWebhook) => {
				onWebhookSaved(savedWebhook);
				show = false;
				resetForm();
				toast.success(`Webhook ${isEditing ? 'updated' : 'created'} successfully`);
			})
			.catch((error) => {
				console.error(`Failed to ${isEditing ? 'update' : 'create'} webhook:`, error);
				toast.error(`Failed to ${isEditing ? 'update' : 'create'} webhook`);
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleClose() {
		show = false;
		resetForm();
	}

	function handleEventChange(event: string) {
		if (selectedEvents.includes(event)) {
			selectedEvents = selectedEvents.filter(e => e !== event);
		} else {
			selectedEvents = [...selectedEvents, event];
		}
		errors.events = '';
	}
</script>

<Modal 
	bind:show 
	size="medium" 
	footer={{
		cancel: {
			text: 'Cancel'
		},
		confirm: {
			text: submitButtonText,
		}
	}}
	title={modalTitle}
	on:cancel={handleClose}
	on:confirm={handleSubmit}>

	<div class="modal-content">
		<SplitControl label="URL" error={errors.url}>
			<TextInput
				bind:value={url}
				placeholder="https://example.com/webhook"
				block
				disabled={loading}
			/>
		</SplitControl>

		<SplitControl label="Description (optional)">
			<TextInput
				bind:value={description}
				placeholder="Enter a description for this webhook"
				block
				disabled={loading}
			/>
		</SplitControl>

		<SplitControl label="Events" error={errors.events}>
			<div class="events-section">
				<div class="events-grid">
					{#each availableEvents as event}
						<div class="event-checkbox">
							<Checkbox
								checked={selectedEvents.includes(event)}
								disabled={loading}
								on:change={() => handleEventChange(event)}
							>
								{event}
							</Checkbox>
						</div>
					{/each}
				</div>
				
				{#if selectedEvents.length > 0}
					<div class="selected-count">
						{selectedEvents.length} event{selectedEvents.length === 1 ? '' : 's'} selected
					</div>
				{/if}
			</div>
		</SplitControl>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}

	.events-section {
		border: 1px solid var(--border);
		border-radius: 6px;
		padding: 16px;
		background: var(--bg);
	}

	.events-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 12px;
	}

	.event-checkbox {
		display: flex;
		align-items: center;
	}

	.selected-count {
		margin-top: 12px;
		padding-top: 12px;
		border-top: 1px solid var(--border);
		font-size: 14px;
		color: var(--text-light);
		font-weight: 500;
	}

	@media (max-width: 640px) {
		.events-grid {
			grid-template-columns: 1fr;
		}
	}
</style> 