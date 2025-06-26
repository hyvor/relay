<script lang="ts">
	import {
		Button,
		Modal,
		TextInput,
		SplitControl,
		toast,
		confirm,
		Callout,
		IconButton
	} from '@hyvor/design/components';
	import IconPlus from '@hyvor/icons/IconPlus';
	import IconCopy from '@hyvor/icons/IconCopy';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import CreateApiKeyModal from './CreateApiKeyModal.svelte';
	import APIKeyList from './APIKeyList.svelte';
	import type { ApiKey } from '../../types';
	import { getApiKeys, updateApiKey, deleteApiKey } from '../../lib/actions/apiKeyActions';
	import { onMount } from 'svelte';
	import { copyAndToast } from '../../lib/helpers/copy';

	let apiKeys: ApiKey[] = $state([]);
	let loading = $state(true);
	let showCreateModal = $state(false);
	let showApiKeyModal = $state(false);
	let newApiKey: ApiKey | null = $state(null);

	const scopes = [
		{ value: 'send_email', label: 'Send Email' },
		{ value: 'full', label: 'Full Access' }
	];

	onMount(() => {
		loadApiKeys();
	});

	function loadApiKeys() {
		loading = true;
		getApiKeys()
			.then((keys) => {
				apiKeys = keys;
			})
			.catch((error) => {
				console.error('Failed to load API keys:', error);
				toast.error('Failed to load API keys');
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleApiKeyCreated(apiKey: ApiKey) {
		newApiKey = apiKey;
		showApiKeyModal = true;
		loadApiKeys();
	}

	function handleToggleEnabled(apiKey: ApiKey) {
		const newEnabledState = !apiKey.is_enabled;
		updateApiKey(apiKey.id, newEnabledState)
			.then(() => {
				apiKeys = apiKeys.map((key) =>
					key.id === apiKey.id ? { ...key, is_enabled: newEnabledState } : key
				);
				toast.success(`API key ${apiKey.is_enabled ? 'disabled' : 'enabled'}`);
			})
			.catch((error) => {
				console.error('Failed to update API key:', error);
				toast.error('Failed to update API key');
			});
	}

	async function handleDeleteApiKey(apiKey: ApiKey) {
		const confirmed = await confirm({
			title: 'Delete API key',
			content: `Are you sure you want to delete the API key "${apiKey.name}"?`,
			confirmText: 'Delete',
			cancelText: 'Cancel',
			danger: true
		});

		if (confirmed) {
			deleteApiKey(apiKey.id)
				.then(() => {
					loadApiKeys();
					toast.success('API key deleted');
				})
				.catch((error) => {
					console.error('Failed to delete API key:', error);
					toast.error('Failed to delete API key');
				});
		}
	}

	function getScopeLabel(scope: string) {
		return scopes.find((s) => s.value === scope)?.label || scope;
	}
</script>

<SingleBox>
	<div class="top">
		<Button variant="fill" on:click={() => (showCreateModal = true)}>
			<IconPlus size={16} />
			Create API Key
		</Button>
	</div>

	<div class="content">
		<APIKeyList
			{apiKeys}
			{loading}
			onToggleEnabled={handleToggleEnabled}
			onDelete={handleDeleteApiKey}
		/>
	</div>
</SingleBox>

<CreateApiKeyModal bind:show={showCreateModal} onApiKeyCreated={handleApiKeyCreated} />

<!-- Show New API Key Modal -->
{#if showApiKeyModal && newApiKey}
	<Modal
		title="Your New API Key"
		bind:show={showApiKeyModal}
		size="medium"
		footer={{
			cancel: {
				text: 'Close'
			},
			confirm: false
		}}
	>
		<div class="modal-content">
			<div class="warning-box">
				<strong>Important:</strong> This is the only time you'll see this API key. Make sure to copy
				it and store it securely.
			</div>

			<SplitControl label="API Key">
				<div class="key-input-group">
					<TextInput value={newApiKey.key || ''} readonly block />
					<IconButton
						size="small"
						color="input"
						style="margin-left:4px;"
						on:click={() => copyAndToast(newApiKey?.key || '', 'API Key copied')}
					>
						<IconCopy size={12} />
					</IconButton>
				</div>
			</SplitControl>

			<SplitControl label="Name">
				<span>{newApiKey.name}</span>
			</SplitControl>

			<SplitControl label="Scope">
				<span>{getScopeLabel(newApiKey.scope)}</span>
			</SplitControl>
		</div>
	</Modal>
{/if}

<style>
	.top {
		display: flex;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}

	.content {
		padding: 30px;
	}

	.modal-content {
		padding: 20px 0;
	}

	.warning-box {
		padding: 16px;
		background: var(--orange-50);
		border: 1px solid var(--orange-200);
		border-radius: 6px;
		color: var(--orange-900);
		margin-bottom: 20px;
	}

	.key-input-group {
		display: flex;
		gap: 8px;
		align-items: flex-end;
	}

	.api-key-details {
		padding: 16px;
		background: var(--bg-light);
		border-radius: 6px;
		border: 1px solid var(--border);
		margin-top: 20px;
	}

	.api-key-details p {
		margin: 0 0 8px 0;
		font-size: 14px;
	}

	.api-key-details p:last-child {
		margin-bottom: 0;
	}
</style>
