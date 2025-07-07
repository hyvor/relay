<script lang="ts">
	import {
		Modal,
		Button,
		TextInput,
		SplitControl,
		ActionList,
		ActionListItem,
		toast,
		Checkbox,
		InputGroup,
		Callout
	} from '@hyvor/design/components';
	import { createApiKey } from '../../lib/actions/apiKeyActions';
	import type { ApiKey } from '../../types';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	interface Props {
		show: boolean;
		onApiKeyCreated?: (apiKey: ApiKey) => void;
	}

	let { show = $bindable(), onApiKeyCreated = () => {} }: Props = $props();

	let name = $state('');
	let selectedScopes = $state<string[]>(['sends.send']);
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});

	const appConfig = getAppConfig();
	const scopes = appConfig.app?.api_keys?.scopes || [];

	function getScopeDescription(scope: string): string | null {
		const descriptionMap: Record<string, string> = {
			'sends.send': 'for sending emails'
		};
		return descriptionMap[scope] || null;
	}

	function resetForm() {
		name = '';
		selectedScopes = ['sends.send'];
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
		createApiKey(name.trim(), selectedScopes)
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

	function handleScopeToggle(scopeValue: string) {
		if (selectedScopes.includes(scopeValue)) {
			selectedScopes = selectedScopes.filter(s => s !== scopeValue);
		} else {
			selectedScopes = [...selectedScopes, scopeValue];
		}
	}
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
			text: 'Create API Key'
		}
	}}
	title="Create API Key"
	on:cancel={handleClose}
	on:confirm={handleSubmit}
>
	<div class="modal-content">
		<SplitControl
			label="Name"
			caption="A descriptive name to identify this API key"
			error={errors.name}
		>
			<TextInput bind:value={name} placeholder="Enter API key name" block disabled={loading} />
		</SplitControl>

		<SplitControl
			label="Scopes"
			caption="Select what actions this API key can perform"
			error={errors.scopes}
		>
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
									<span class="scope-description">{getScopeDescription(scope)}</span>
								{/if}
							</div>
						</Checkbox>
					</div>
				{/each}
			</div>
		</SplitControl>

		{#if selectedScopes.length > 0}
			<Callout type="info">
				This API key will have access to: {selectedScopes.join(', ')}
			</Callout>
		{/if}
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
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
