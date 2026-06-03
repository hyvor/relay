<script lang="ts">
	import { onMount } from 'svelte';
	import {
		Modal,
		TextInput,
		SplitControl,
		toast,
		Checkbox,
		Switch,
		Button,
		IconButton
	} from '@hyvor/design/components';
	import IconX from '@hyvor/icons/IconX';
	import { createApiKey, updateApiKey } from '../../lib/actions/apiKeyActions';
	import type { ApiKey } from '../../types';
	import { getAppConfig } from '../../lib/stores/consoleStore';
	import { validateAllowedIpEntry, cidrAddressCount } from './allowedIp';
	import AddedIpRow from './AddedIpRow.svelte';

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
	let allowedIps = $state<string[]>(['10.0.0.1/2']);
	let ipInput = $state('');
	let ipError = $state<string | undefined>();
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});
	let nameInputWrapper: HTMLElement;

	const appConfig = getAppConfig();
	const scopes = appConfig.app?.api_keys?.scopes || [];

	$effect(() => {
		if (editingApiKey) {
			name = editingApiKey.name;
			selectedScopes = [...editingApiKey.scopes];
			isEnabled = editingApiKey.is_enabled;
			allowedIps = [...(editingApiKey.allowed_ips ?? [])];
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
		//allowedIps = [];
		ipInput = '';
		ipError = undefined;
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

		if (selectedScopes.includes('sends.send') && allowedIps.length === 0) {
			errors.allowed_ips =
				'At least one allowed IP is required when "sends.send" scope is enabled.';
		}

		return Object.keys(errors).length === 0;
	}

	function handleAddIp() {
		const entry = ipInput.trim();
		if (entry === '') return;
		const error = validateAllowedIpEntry(entry);
		if (error) {
			ipError = error;
			return;
		}
		if (allowedIps.includes(entry)) {
			ipError = 'This entry is already in the list.';
			return;
		}
		allowedIps = [...allowedIps, entry];
		ipInput = '';
		ipError = undefined;
		if (errors.allowed_ips) {
			const next = { ...errors };
			delete next.allowed_ips;
			errors = next;
		}
	}

	function handleRemoveIp(entry: string) {
		allowedIps = allowedIps.filter((e) => e !== entry);
	}

	function handleIpKeydown(event: KeyboardEvent) {
		if (event.key === 'Enter') {
			event.preventDefault();
			handleAddIp();
		}
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
					is_enabled: isEnabled,
					allowed_ips: allowedIps
				})
			: createApiKey(name.trim(), selectedScopes, allowedIps);

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
	const sendsSendSelected = $derived(selectedScopes.includes('sends.send'));
	const confirmDisabled = $derived(
		loading ||
			!name.trim() ||
			selectedScopes.length === 0 ||
			(sendsSendSelected && allowedIps.length === 0)
	);

	onMount(() => {
		nameInputWrapper?.querySelector('input')?.focus();
	});
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
			text: confirmText,
			disabled: confirmDisabled
		}
	}}
	title={modalTitle}
	on:cancel={handleClose}
	on:confirm={handleSubmit}
	closeOnOutsideClick={false}
>
	<div class="modal-content">
		<SplitControl
			label="Name"
			caption="A descriptive name to identify this API key"
			error={errors.name}
			column
		>
			<div bind:this={nameInputWrapper}>
				<TextInput
					bind:value={name}
					placeholder="Enter API key name"
					block
					disabled={loading}
				/>
			</div>
		</SplitControl>

		<SplitControl
			label="Scopes"
			caption="Select what actions this API key can perform"
			error={errors.scopes}
			column
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
				{#each [scopes.slice(0, Math.floor(scopes.length / 2)), scopes.slice(Math.floor(scopes.length / 2))] as scopeCol}
					<div class="scope-column">
						{#each scopeCol as scope}
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
				{/each}
			</div>
		</SplitControl>

		<SplitControl
			label={sendsSendSelected ? 'Allowed IPs (required)' : 'Allowed IPs'}
			caption="Choose which IP addresses are allowed to use this API key (HTTP and SMTP). CIDR ranges are supported (max /24 for IPv4, /48 for IPv6)."
			error={errors.allowed_ips}
			column
		>
			<div class="ip-input-row">
				<TextInput
					bind:value={ipInput}
					placeholder="e.g. 203.0.113.5 or 2001:db8::/64"
					block
					disabled={loading}
					on:keydown={handleIpKeydown}
				/>
				<Button
					variant="outline"
					on:click={handleAddIp}
					disabled={loading || ipInput.trim() === ''}
				>
					Add
				</Button>
			</div>
			{#if ipError}
				<div class="ip-error">{ipError}</div>
			{/if}
			{#if allowedIps.length > 0}
				<div class="ip-list">
					{#each allowedIps as entry, i (entry)}
						<AddedIpRow index={i + 1} {entry} onremove={handleRemoveIp} />
					{/each}
				</div>
			{/if}
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
		max-height: 70vh;
		overflow-y: auto;
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
		display: grid;
		grid-template-columns: 1fr 1fr;
	}

	.scope-column {
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

	.ip-input-row {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	.ip-error {
		margin-top: 6px;
		font-size: 12px;
		color: var(--red);
	}

	.ip-list {
		display: flex;
		flex-direction: column;
		gap: 4px;
		margin-top: 10px;
	}
</style>
