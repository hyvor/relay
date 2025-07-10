<script lang="ts">
	import { Modal, TextInput, SplitControl, toast } from '@hyvor/design/components';
	import { createDomain } from '../../lib/actions/domainActions';
	import type { Domain } from '../../types';

	interface Props {
		show: boolean;
		onDomainCreated?: (domain: Domain) => void;
	}

	let { show = $bindable(), onDomainCreated = () => {} }: Props = $props();

	let domain = $state('');
	let loading = $state(false);
	let errors = $state<Record<string, string>>({});
	let input: HTMLInputElement | null = $state(null);

	function resetForm() {
		domain = '';
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

		createDomain(domain.trim())
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
			/>
		</SplitControl>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}
</style>
