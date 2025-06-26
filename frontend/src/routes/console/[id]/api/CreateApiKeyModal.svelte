<script lang="ts">
	import {
		Modal,
		Button,
		TextInput,
		SplitControl,
		ActionList,
		ActionListItem,
		toast,
		Radio,
		InputGroup,
		Callout
	} from '@hyvor/design/components';
	import { createApiKey } from '../../lib/actions/apiKeyActions';
	import type { ApiKey, ApiKeyScope } from '../../types';

	interface Props {
		show: boolean;
		onApiKeyCreated?: (apiKey: ApiKey) => void;
	}

	let { show = $bindable(), onApiKeyCreated = () => {} }: Props = $props();

	let name = $state('');
	let scope = $state<ApiKeyScope>('send_email');
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});

	const scopes: Array<{ value: ApiKeyScope; label: string }> = [
		{ value: 'send_email', label: 'Send Email' },
		{ value: 'full', label: 'Full Access' }
	];

	function resetForm() {
		name = '';
		scope = 'send_email';
		errors = {};
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
			label="Scope"
			caption="Define what actions this API key can perform"
			error={errors.scope}
		>
			<InputGroup>
				{#each scopes as scopeOption}
					<Radio name="scope" value={scopeOption.value} bind:group={scope} disabled={loading}>
						{scopeOption.label}
					</Radio>
				{/each}
			</InputGroup>
		</SplitControl>
		<Callout type="info">
			{#if scope === 'send_email'}
				This API key can only be used to send emails.
			{:else if scope === 'full'}
				This API key has full access to all project resources.
			{/if}
		</Callout>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}
</style>
