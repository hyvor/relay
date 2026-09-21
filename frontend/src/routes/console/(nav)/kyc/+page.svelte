<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import {
		TextInput,
		Textarea,
		Select,
		SplitControl,
		FormControl,
		Validation,
		Button,
		Callout,
		Loader,
		toast,
		Tooltip,
		InputGroup,
		Radio,
		Checkbox

	} from '@hyvor/design/components';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import CardCollector from './CardCollector.svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';
	import { getKyc, getKycCountries, submitKyc } from '../../lib/actions/kycActions';
	import type { Kyc, KycAccountType, KycContentOwnership } from '../../types';

	let loading = $state(true);
	let saving = $state(false);
	let existingKyc = $state<Kyc | null>(null);

	let currentStep = $state<1 | 2>(1);

	let detailsConfirmed = $state(false);
	let cardAdded = $state(false);

	let accountType = $state<KycAccountType>('individual');
	let name = $state('');
	let country = $state('');
	let address = $state('');
	let website = $state('');
	let email = $state('');
	let contentOwnership = $state<KycContentOwnership[]>([]);
	let sendingTransactional = $state(false);
	let sendingDistributional = $state(false);
	let useCase = $state('');

	let errors = $state<Record<string, string>>({});

	let countries = $state<string[]>([]);
	const countryOptions = $derived(countries.map((name) => ({ value: name, label: name })));

	const formUnchanged = $derived.by(() => {
		const kyc = existingKyc;

		if (!kyc) {
			return false;
		}

		return (
			accountType === kyc.account_type &&
			name.trim() === kyc.name &&
			country === kyc.country &&
			address.trim() === kyc.address &&
			website.trim() === kyc.website &&
			email.trim() === kyc.email &&
			contentOwnership.length === kyc.content_ownership.length &&
			contentOwnership.every((type) => kyc.content_ownership.includes(type)) &&
			useCase.trim() === kyc.use_case &&
			sendingTransactional === kyc.sending_transactional &&
			sendingDistributional === kyc.sending_distributional
		);
	});

	const submitDisabledReason = $derived(
		!cardAdded
			? 'Save your card to proceed'
			: formUnchanged
				? 'Update your details before resubmitting'
				: null
	);

	function fillForm(kyc: Kyc) {
		name = kyc.name;
		accountType = kyc.account_type;
		country = kyc.country;
		address = kyc.address;
		website = kyc.website;
		email = kyc.email;
		contentOwnership = kyc.content_ownership;
		sendingTransactional = kyc.sending_transactional;
		sendingDistributional = kyc.sending_distributional;
		useCase = kyc.use_case;
	}

	function toggleContentOwnership(type: KycContentOwnership) {
		if (contentOwnership.includes(type)) {
			contentOwnership = contentOwnership.filter((t) => t !== type);
		} else {
			contentOwnership = [...contentOwnership, type];
		}
	}

	function goToStep(step: 1 | 2) {
		if (step === 2 && !existingKyc && !detailsConfirmed) {
			return;
		}
		currentStep = step;
	}

	onMount(() => {
		if (getAppConfig().deployment !== 'cloud') {
			goto('/console');
			return;
		}

		getKycCountries()
			.then((res) => {
				countries = res;
			})
			.catch((error) => {
				toast.error('Failed to load countries: ' + error.message);
			});

		getKyc()
			.then((res) => {
				existingKyc = res;
				if (res) {
					fillForm(res);
					detailsConfirmed = true;
				}
			})
			.catch((error) => {
				toast.error('Failed to load KYC details: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	});

	function validate(): boolean {
		errors = {};

		if (!name.trim()) {
			errors.name = 'Full name is required';
		}

		if (accountType === 'business' && !name.trim()) {
			errors.name = 'Business name is required for businesses';
		}

		if (!country) {
			errors.country = 'Country is required';
		}

		if (!address.trim()) {
			errors.address = 'Address is required';
		}

		if (!website.trim()) {
			errors.website = 'Website is required';
		} else if (
			!/^https?:\/\/.+/i.test(website.trim()) &&
			!/^([a-z0-9-]+\.)+[a-z]{2,}(\/.*)?$/i.test(website.trim())
		) {
			errors.website = 'Enter a valid website, e.g. https://example.com or www.example.com';
		}

		if (!email.trim()) {
			errors.email = 'Email is required';
		} else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
			errors.email = 'Enter a valid email address';
		}

		if (contentOwnership.length === 0) {
			errors.contentOwnership = 'Select at least one option';
		}

		if (!sendingTransactional && !sendingDistributional) {
			errors.sendingType = 'Select at least one sending type';
		}

		if (useCase.trim() === '') {
			errors.useCase = 'Use case is required';
		}

		return Object.keys(errors).length === 0;
	}

	function handleNext() {
		if (!validate()) {
			return;
		}

		detailsConfirmed = true;
		currentStep = 2;
	}

	function handleSubmit() {
		if (saving || !cardAdded || formUnchanged) {
			return;
		}

		saving = true;

		submitKyc({
			account_type: accountType,
			name: name.trim(),
			country,
			address: address.trim(),
			website: website.trim(),
			email: email.trim(),
			content_ownership: contentOwnership,
			sending_transactional: sendingTransactional,
			sending_distributional: sendingDistributional,
			use_case: useCase
		})
			.then((res) => {
				existingKyc = res;
				toast.success('KYC submitted. We will review your details shortly.');
			})
			.catch((error) => {
				toast.error(error.message ?? 'Failed to submit KYC');
			})
			.finally(() => {
				saving = false;
			});
	}

	function handleCardAdded() {
		cardAdded = true;
		toast.success('Card added successfully.');
	}

	function handleCardError(message: string) {
		toast.error(message);
	}
</script>

<SingleBox>
	<div class="top">
		<h1>KYC Verification</h1>
		<p class="subtitle">
			We need a few details about you or your business, along with your payment details, 
			before you can send emails on Hyvor Relay Cloud. This helps us verify legitimate 
			senders and prevent misuse of the platform.
		</p>
	</div>

	<div class="body">
		{#if loading}
			<div class="loader-wrap">
				<Loader size="large" />
			</div>
		{:else}
			<div class="steps">
				<button
					type="button"
					class="step"
					class:active={currentStep === 1}
					onclick={() => goToStep(1)}
				>
					<span class="step-num">1</span>
					<span class="step-label">Your details</span>
				</button>
				<div class="step-connector"></div>
				<button
					type="button"
					class="step"
					class:active={currentStep === 2}
					disabled={!existingKyc && !detailsConfirmed}
					onclick={() => goToStep(2)}
				>
					<span class="step-num">2</span>
					<span class="step-label">Payment details</span>
				</button>
			</div>

			{#if currentStep === 1}
				{#if existingKyc?.status === 'pending'}
					<div class="callout-wrap">
						<Callout type="info">
							Your KYC submission is under review. You can still update the details
							below and resubmit while it's pending.
						</Callout>
					</div>
				{:else if existingKyc?.status === 'approved'}
					<div class="callout-wrap">
						<Callout type="success">
							Your KYC has been approved. You can still update the details below
							and resubmit if anything has changed.
						</Callout>
					</div>
				{:else if existingKyc?.status === 'rejected'}
					<div class="callout-wrap">
						<Callout type="danger">
							Your KYC submission was rejected.
							{#if existingKyc.reject_reason}
								Reason: {existingKyc.reject_reason}
							{/if}
							Please review the details below and resubmit.
						</Callout>
					</div>
				{/if}

				<div class="form">
					<SplitControl
						label="Account type"
						caption="Are you sending emails as an individual or a business?"
					>
						<InputGroup>
							<span class="radio-wrap">
								<Radio
									name="account-type"
									value="individual"
									bind:group={accountType}
								>Individual</Radio>
								<Radio
									name="account-type"
									value="business"
									bind:group={accountType}
								>Business</Radio>
							</span>
						</InputGroup>
					</SplitControl>

					<SplitControl 
						label={accountType === 'individual' ? 'Full name' : 'Business name'} 
						caption={accountType === 'individual' ? 'Your full legal name' : 'Your company or organization\'s legal name'}
					>
						<FormControl>
							<TextInput
								bind:value={name}
								block
								placeholder={accountType === 'individual' ? 'John Doe' : 'HYVOR'}
							/>
							{#if errors.name}
								<Validation state="error">{errors.name}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Country" caption={'Country of ' + (accountType === 'individual' ? 'residence' : 'incorporation')}>
						<FormControl>
							<Select
								bind:value={country}
								options={countryOptions}
								placeholder="Select a country"
								block
								state={errors.country ? 'error' : 'default'}
							/>
							{#if errors.country}
								<Validation state="error">{errors.country}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Address" caption={'Your ' + (accountType === 'individual' ? 'residential' : 'business') + ' address'}>
						<FormControl>
							<Textarea
								bind:value={address}
								block
								rows={3}
								placeholder="123 Main Street, City, Postal Code"
							/>
							{#if errors.address}
								<Validation state="error">{errors.address}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Website" caption={'Your ' + (accountType === 'individual' ? 'personal' : 'business') + ' website'}>
						<FormControl>
							<TextInput
								bind:value={website}
								block
								placeholder={accountType === 'individual' ? 'https://yourpersonalwebsite.com' : 'https://yourbusinesswebsite.com'}
							/>
							{#if errors.website}
								<Validation state="error">{errors.website}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Contact email" caption="Who should we contact if there's an issue with your approval or we need more information?">
						<FormControl>
							<TextInput
								bind:value={email}
								block
								type="email"
								placeholder="you@example.com"
							/>
							{#if errors.email}
								<Validation state="error">{errors.email}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl
						label="Content Ownership"
						caption="Who writes the content you send? You or anyone from your organization or a third party who uses your platform."
					>
						<FormControl>
							<span class="radio-wrap">
								<Checkbox
									checked={contentOwnership.includes('self')}
									on:change={() => toggleContentOwnership('self')}
								>Our-self</Checkbox>
								<Checkbox
									checked={contentOwnership.includes('third_party')}
									on:change={() => toggleContentOwnership('third_party')}
								>Third Party</Checkbox>
							</span>
							{#if errors.contentOwnership}
								<Validation state="error">{errors.contentOwnership}</Validation>
							{/if}
						</FormControl>

						{#if contentOwnership.includes('third_party')}
							<div class="callout-wrap-top">
								<Callout type="danger">
									Hyvor Relay cloud version is currently not intended for use by third parties outside you or your organization's
									employees. For other use cases, please consider self-hosting Hyvor Relay instead.
								</Callout>
							</div>
						{/if}
					</SplitControl>

					<SplitControl label="Sending Type" caption="Type of emails you plan to send.">
						<FormControl>
							<span class="radio-wrap">
								<Checkbox
									checked={sendingTransactional}
									on:change={() => (sendingTransactional = !sendingTransactional)}
								>Transactional</Checkbox>
								<Checkbox
									checked={sendingDistributional}
									on:change={() => (sendingDistributional = !sendingDistributional)}
								>Distributional</Checkbox>
							</span>
							{#if errors.sendingType}
								<Validation state="error">{errors.sendingType}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Use case" caption="Describe your use case for Hyvor Relay">

						{#if sendingDistributional}
							<div class="callout-wrap-bottom">
								<Callout type="info">
									Describe your use case in detail. Include the type of content you'll send (e.g.newsletters, announcements, 
									marketing campaigns), how recipients opted in to receive emails, your expected sending volume and frequency, 
									and the platform or application they'll be sent from.
								</Callout>
							</div>
						{/if}

						<Textarea
							name="use-case"
							bind:value={useCase}
							block
						/>
						{#if errors.useCase}
							<Validation state="error">{errors.useCase}</Validation>
						{/if}

					</SplitControl>

				</div>

				<div class="actions">
					<Button
						color="accent"
						variant="fill"
						on:click={handleNext}
						disabled={contentOwnership.includes('third_party')}
					>Next</Button>
				</div>
			{:else}
				<div class="kyc-card-wrap">
					{#if existingKyc?.status === 'rejected' && formUnchanged}
						<div class="callout-wrap">
							<Callout type="danger">
								Your KYC submission was rejected.
								{#if existingKyc.reject_reason}
									Reason: {existingKyc.reject_reason}
								{/if}
								Go back to Step 1 to update your details, then submit again below.
							</Callout>
						</div>
					{:else if existingKyc?.status === 'approved' && formUnchanged}
						<div class="callout-wrap">
							<Callout type="success">
								Your KYC has been approved. Your card will be charged automatically
								for a Starter plan subscription. You may upgrade your plan at <a href="/billing">
								Billing</a> later. You can still update your details and resubmit if
								anything has changed.
							</Callout>
						</div>
					{:else if existingKyc?.status === 'pending'}
						<div class="callout-wrap">
							<Callout type="info">
								Your KYC submission is under review. You can still update your
								details and resubmit.
							</Callout>
						</div>
					{:else if !existingKyc}
						<div class="callout-wrap">
							<Callout type="info">
								Add your payment details below, then submit to complete your KYC
								verification. Your card will be charged automatically for the Starter 
								plan once it's approved. If your organization already has a payment method
								added, you can continue with the existing payment method.
							</Callout>
						</div>
					{/if}

					<div class="payment-box">
						<CardCollector onSuccess={handleCardAdded} onError={handleCardError} bind:cardAdded={cardAdded} />
					</div>
				</div>

				<div class="actions space-between">
					<Button variant="outline" color="gray" on:click={() => goToStep(1)}>Back</Button
					>
					<div class="submit-wrap">
						<Tooltip text={submitDisabledReason ?? ''} disabled={submitDisabledReason === null}>
							<Button
								color="accent"
								variant="fill"
								disabled={saving || !cardAdded || formUnchanged}
								on:click={handleSubmit}
							>
								{saving
									? 'Submitting...'
									: existingKyc
										? 'Resubmit KYC'
										: 'Submit KYC'}
							</Button>
						</Tooltip>
					</div>
				</div>
			{/if}
		{/if}
	</div>
</SingleBox>

<style>
	.top {
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}

	.top h1 {
		font-size: 18px;
		margin: 0 0 6px;
	}

	.subtitle {
		margin: 0;
		color: var(--text-light);
		font-size: 13px;
	}

	.body {
		padding: 30px;
		flex: 1;
		overflow: auto;
	}

	.loader-wrap {
		display: flex;
		justify-content: center;
		align-items: center;
		height: 100%;
	}

	.steps {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 25px;
	}

	.step {
		display: flex;
		align-items: center;
		gap: 8px;
		background: none;
		border: none;
		padding: 6px 0;
		font: inherit;
		font-weight: 600;
		font-size: 13px;
		color: var(--text-light);
		cursor: pointer;
	}

	.step[disabled] {
		cursor: not-allowed;
		opacity: 0.5;
	}

	.step.active {
		color: var(--text);
	}

	.step-num {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 22px;
		height: 22px;
		border-radius: 50%;
		background: var(--accent-lightest);
		color: var(--text-light);
		font-size: 12px;
	}

	.step.active .step-num {
		background: var(--accent);
		color: var(--accent-text);
	}

	.step-connector {
		width: 40px;
		height: 1px;
		background: var(--border);
		margin: 0 12px;
	}

	.callout-wrap {
		margin-bottom: 20px;
	}

	.radio-wrap {
		display: flex; 
		gap: 3rem;
	}

	.callout-wrap-top {
		margin-top: 20px;
	}

	.callout-wrap-bottom {
		margin-bottom: 20px;
	}

	.payment-box {
		overflow: hidden;
	}

	.actions {
		display: flex;
		justify-content: flex-end;
		margin-top: 20px;
	}

	.actions.space-between {
		justify-content: space-between;
	}

	.submit-wrap {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
	}

	.kyc-card-wrap {
		max-width: 600px;
		margin: 0 auto;
	}
</style>
