<script lang="ts">
	import { Modal, Button, TextInput, SplitControl, ActionList, ActionListItem, toast } from '@hyvor/design/components';
	import { createApiKey } from '../../lib/actions/apiKeyActions';
	import type { ApiKey, ApiKeyScope } from '../../types';
	import Selector from '../../@components/content/Selector.svelte';

	interface Props {
		show: boolean;
		onApiKeyCreated?: (apiKey: ApiKey) => void;
	}

	let { show = $bindable(), onApiKeyCreated = () => {} }: Props = $props();

	let name = $state('');
	let scope = $state<ApiKeyScope>('send_email');
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});
	let showScopeSelector = $state(false);

	const scopes: Array<{ value: ApiKeyScope; label: string }> = [
		{ value: 'send_email', label: 'Send Email' },
		{ value: 'full', label: 'Full Access' }
	];

	function resetForm() {
		name = '';
		scope = 'send_email';
		errors = {};
		showScopeSelector = false;
	}

	function validateForm(): boolean {
		errors = {};

		if (!name.trim()) {
			errors.name = 'Name is required';
		} else if (name.trim().length > 255) {
			errors.name = 'Name must be less than 255 characters';
		}

		if (!scope) {
			errors.scope = 'Scope is required';
		}

		return Object.keys(errors).length === 0;
	}

	function handleSubmit() {
		if (!validateForm()) {
			return;
		}

		loading = true;
		createApiKey(name.trim(), scope)
			.then((apiKey) => {
				onApiKeyCreated(apiKey);
				show = false;
				resetForm();
				toast.success('API key created successfully');
			})
			.catch((error) => {
				console.error('Failed to create API key:', error);
				toast.error('Failed to create API key');
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleClose() {
		show = false;
		resetForm();
	}

	function selectScope(selectedScope: ApiKeyScope) {
		scope = selectedScope;
		showScopeSelector = false;
		errors.scope = '';
	}

	function getScopeLabel(scopeValue: ApiKeyScope) {
		return scopes.find(s => s.value === scopeValue)?.label || scopeValue;
	}

	function handleScopeTriggerClick() {
		showScopeSelector = !showScopeSelector;
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
			text: 'Create API Key',
		}
	}}
	title="Create API Key"
	on:cancel={handleClose}
	on:confirm={handleSubmit}>

	<div class="modal-content">
		<SplitControl label="Name" error={errors.name}>
			<TextInput
				bind:value={name}
				placeholder="Enter API key name"
				block
				disabled={loading}
			/>
		</SplitControl>

		<SplitControl label="Scope" error={errors.scope}>
			<Selector
				name="Scope"
				bind:show={showScopeSelector}
				value={getScopeLabel(scope)}
				width={400}
				disabled={loading}
				handleTriggerClick={handleScopeTriggerClick}
			>
				<ActionList selection="single" selectionAlign="end">
					{#each scopes as scopeOption}
						<ActionListItem
							on:click={() => selectScope(scopeOption.value)}
							selected={scope === scopeOption.value}
						>
							{scopeOption.label}
						</ActionListItem>
					{/each}
				</ActionList>
			</Selector>
		</SplitControl>

		<div class="scope-description">
			{#if scope === 'send_email'}
				<p>This API key can only be used to send emails.</p>
			{:else if scope === 'full'}
				<p>This API key has full access to all project resources.</p>
			{/if}
		</div>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}

	.scope-description {
		margin-top: 16px;
		padding: 12px;
		background: var(--bg-light);
		border-radius: 6px;
		border: 1px solid var(--border);
	}

	.scope-description p {
		margin: 0;
		font-size: 14px;
		color: var(--text-light);
	}
</style>