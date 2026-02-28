<script lang="ts">
	import { Modal, TextInput, Textarea, SplitControl, Button, toast } from '@hyvor/design/components';
	import { createDomain } from '../../lib/actions/domainActions';
	import type { Domain } from '../../types';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconCaretRight from '@hyvor/icons/IconCaretRight';

	interface Props {
		show: boolean;
		onDomainCreated?: (domain: Domain) => void;
	}

	let { show = $bindable(), onDomainCreated = () => {} }: Props = $props();

	let domain = $state('');
	let dkimSelector = $state('');
	let dkimPrivateKey = $state('');
	let showAdvanced = $state(false);
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});
	let input: HTMLInputElement | null = $state(null);

	function resetForm() {
		domain = '';
		dkimSelector = '';
		dkimPrivateKey = '';
		showAdvanced = false;
		errors = {};
	}

	function validateForm(): boolean {
		errors = {};

		if (!domain.trim()) {
			errors.domain = 'Domain is required';
		} else {
			// Basic domain validation
			const domainRegex =
				/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9](?:\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9])*$/;
			if (!domainRegex.test(domain.trim())) {
				errors.domain = 'Please enter a valid domain name';
			}
		}

		return Object.keys(errors).length === 0;
	}

	function handleSubmit() {
		if (!validateForm()) {
			return;
		}

		loading = true;

		createDomain(domain.trim(), dkimSelector.trim() || undefined, dkimPrivateKey.trim() || undefined)
			.then((newDomain) => {
				onDomainCreated(newDomain);
				toast.success('Domain created successfully');
				handleClose();
			})
			.catch((error) => {
				console.error(error);
				toast.error('Failed to create domain: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleClose() {
		show = false;
		resetForm();
	}

	$effect(() => {
		if (show && input) {
			input.focus();
		}
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
			text: 'Create Domain'
		}
	}}
	title="Create Domain"
	on:cancel={handleClose}
	on:confirm={handleSubmit}
>
	<div class="modal-content">
		<SplitControl
			label="Domain"
			caption="Enter the domain you want to use for sending emails"
			error={errors.domain}
		>
			<TextInput
				bind:value={domain}
				placeholder="example.com"
				block
				disabled={loading}
				bind:input={input!}
				on:keydown={(e) => {
					if (e.key === 'Enter') {
						e.preventDefault();
						handleSubmit();
					}
				}}
			/>
		</SplitControl>
		<div class="advanced-toggle">
			<Button
				variant="invisible"
				size="small"
				on:click={() => (showAdvanced = !showAdvanced)}
			>
				{showAdvanced ? 'Hide Advanced' : 'Advanced'}

				{#snippet end()}
					{#if showAdvanced}
						<IconCaretDown size={12} />
					{:else}
						<IconCaretRight size={12} />
					{/if}
				{/snippet}
			</Button>
		</div>
		{#if showAdvanced}
			<SplitControl
				label="DKIM Selector"
				caption="Custom DKIM selector. Auto-generated if not provided."
				error={errors.dkim_selector}
			>
				<TextInput
					bind:value={dkimSelector}
					placeholder="dkim-selector"
					block
					disabled={loading}
				/>
			</SplitControl>
			<SplitControl
				label="DKIM Private Key"
				caption="Custom RSA private key in PEM format. Auto-generated if not provided."
			>
				<Textarea
					bind:value={dkimPrivateKey}
					placeholder="-----BEGIN PRIVATE KEY-----"
					block
					disabled={loading}
					rows={5}
				/>
			</SplitControl>
		{/if}
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}
	.advanced-toggle {
		margin-top: 8px;
	}
</style>
