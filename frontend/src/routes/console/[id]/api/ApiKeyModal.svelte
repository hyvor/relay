<script lang="ts">
	import {
		Modal,
		TextInput,
		SplitControl,
		toast,
		Checkbox,
		Switch
	} from '@hyvor/design/components';
	import { createApiKey, updateApiKey } from '../../lib/actions/apiKeyActions';
	import type { ApiKey } from '../../types';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	interface Props {
		show: boolean;
		editingApiKey?: ApiKey | null;
		onApiKeyCreated?: (apiKey: ApiKey) => void;
		onApiKeyUpdated?: (apiKey: ApiKey) => void;
	}

	let {
		show = $bindable(),
		editingApiKey = null,
		onApiKeyCreated = () => {},
		onApiKeyUpdated = () => {}
	}: Props = $props();

	let name = $state('');
	let selectedScopes = $state<string[]>(['sends.send']);
	let isEnabled = $state(true);
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});

	const appConfig = getAppConfig();
	const scopes = appConfig.app?.api_keys?.scopes || [];

	// Watch for editingApiKey changes to populate form
	$effect(() => {
		if (editingApiKey) {
			name = editingApiKey.name;
			selectedScopes = [...editingApiKey.scopes];
			isEnabled = editingApiKey.is_enabled;
		} else {
			resetForm();
		}
	});

	function getScopeDescription(scope: string): string | null {
		const descriptionMap: Record<string, string> = {
			'sends.send': 'for sending emails'
		};
		return descriptionMap[scope] || null;
	}

	function resetForm() {
		name = '';
		selectedScopes = ['sends.send'];
		isEnabled = true;
		errors = {};
	}

	function validateForm(): boolean {
		errors = {};

		if (!name.trim()) {
			errors.name = 'Name is required';
		} else if (name.trim().length > 255) {
			errors.name = 'Name must be less than 255 characters';
		}

		if (selectedScopes.length === 0) {
			errors.scopes = 'At least one scope is required';
		}

		return Object.keys(errors).length === 0;
	}

	function handleSubmit() {
		if (!validateForm()) {
			return;
		}

		loading = true;

		const promise = editingApiKey
			? updateApiKey(editingApiKey.id, {
					name: name.trim(),
					scopes: selectedScopes,
					is_enabled: isEnabled
				})
			: createApiKey(name.trim(), selectedScopes);

		promise
			.then((apiKey) => {
				if (editingApiKey) {
					onApiKeyUpdated(apiKey);
					toast.success('API key updated successfully');
				} else {
					onApiKeyCreated(apiKey);
					toast.success('API key created successfully');
				}
				show = false;
				resetForm();
			})
			.catch((error) => {
				console.error(`Failed to ${editingApiKey ? 'update' : 'create'} API key:`, error);
				toast.error(`Failed to ${editingApiKey ? 'update' : 'create'} API key`);
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleClose() {
		show = false;
		resetForm();
	}

	function handleScopeToggle(scopeValue: string) {
		if (selectedScopes.includes(scopeValue)) {
			selectedScopes = selectedScopes.filter((s) => s !== scopeValue);
		} else {
			selectedScopes = [...selectedScopes, scopeValue];
		}
	}

	const isEditing = $derived(!!editingApiKey);
	const modalTitle = $derived(isEditing ? 'Edit API Key' : 'Create API Key');
	const confirmText = $derived(isEditing ? 'Update API Key' : 'Create API Key');
</script>

<Modal
	bind:show
	{loading}
	size="medium"
	footer={{
		cancel: {
			text: 'Cancel'
		},
		confirm: {
			text: confirmText
		}
	}}
	title={modalTitle}
	on:cancel={handleClose}
	on:confirm={handleSubmit}
>
	<div class="modal-content">
		<SplitControl
			label="Name"
			caption="A descriptive name to identify this API key"
			error={errors.name}
		>
			<TextInput
				bind:value={name}
				placeholder="Enter API key name"
				block
				disabled={loading}
			/>
		</SplitControl>

		<SplitControl
			label="Scopes"
			caption="Select what actions this API key can perform"
			error={errors.scopes}
		>
			<div class="scopes-header">
				<div class="scopes-actions">
					<button
						type="button"
						class="scope-action-btn"
						disabled={loading || selectedScopes.length === scopes.length}
						onclick={() => (selectedScopes = [...scopes])}
					>
						Select all
					</button>
					<button
						type="button"
						class="scope-action-btn"
						disabled={loading || selectedScopes.length === 0}
						onclick={() => (selectedScopes = [])}
					>
						Deselect all
					</button>
				</div>
			</div>
			<div class="scopes-container">
				{#each scopes as scope}
					<div class="scope-item">
						<Checkbox
							checked={selectedScopes.includes(scope)}
							disabled={loading}
							on:change={() => handleScopeToggle(scope)}
						>
							<div class="scope-content">
								<span class="scope-name">{scope}</span>
								{#if getScopeDescription(scope)}
									<span class="scope-description"
										>{getScopeDescription(scope)}</span
									>
								{/if}
							</div>
						</Checkbox>
					</div>
				{/each}
			</div>
		</SplitControl>

		{#if isEditing}
			<SplitControl label="Status" caption="Enable or disable this API key">
				<Switch bind:checked={isEnabled} disabled={loading}>
					{isEnabled ? 'Enabled' : 'Disabled'}
				</Switch>
			</SplitControl>
		{/if}
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}

	.scopes-header {
		margin-bottom: 12px;
	}

	.scopes-actions {
		display: flex;
		gap: 16px;
	}

	.scope-action-btn {
		background: none;
		border: none;
		color: var(--primary);
		cursor: pointer;
		font-size: 14px;
		padding: 0;
		text-decoration: underline;
		transition: color 0.2s;
	}

	.scope-action-btn:hover:not(:disabled) {
		color: var(--primary-dark);
	}

	.scope-action-btn:disabled {
		color: var(--text-light);
		cursor: not-allowed;
		text-decoration: none;
	}

	.scopes-container {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.scope-item {
		display: flex;
		align-items: center;
	}

	.scope-content {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 8px;
	}

	.scope-name {
		font-weight: 500;
	}

	.scope-description {
		font-size: 12px;
		color: var(--text-light);
	}
</style>
