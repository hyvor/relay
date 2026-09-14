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
		Tooltip
	} from '@hyvor/design/components';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import CardCollector from './CardCollector.svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';
	import { getKyc, submitKyc } from '../../lib/actions/kycActions';
	import { COUNTRIES } from '../../lib/countries';
	import type { Kyc, KycBusinessType } from '../../types';

	let loading = $state(true);
	let saving = $state(false);
	let existingKyc = $state<Kyc | null>(null);
	let currentStep = $state<1 | 2>(1);
	// true once the details form has been validated and "Next" clicked in this session,
	// so the user can move between steps without having submitted anything yet
	let detailsConfirmed = $state(false);
	// true once the card component has confirmed a card was added and saved -
	// the KYC can't be (re)submitted before this
	let cardAdded = $state(false);

	let fullName = $state('');
	let businessType = $state<KycBusinessType>('individual');
	let businessName = $state('');
	let country = $state('');
	let address = $state('');
	let phone = $state('');
	let website = $state('');

	let errors = $state<Record<string, string>>({});

	const businessTypeOptions = [
		{ value: 'individual', label: 'Individual' },
		{ value: 'company', label: 'Company / Organization' }
	];

	const countryOptions = COUNTRIES.map((name) => ({ value: name, label: name }));

	const isApproved = $derived(existingKyc?.status === 'approved');

	function fillForm(kyc: Kyc) {
		fullName = kyc.full_name;
		businessType = kyc.business_type;
		businessName = kyc.business_name ?? '';
		country = kyc.country;
		address = kyc.address;
		phone = kyc.phone;
		website = kyc.website;
	}

	function goToStep(step: 1 | 2) {
		// step 2 (payment) only makes sense once the details step has been completed
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

		getKyc()
			.then((res) => {
				existingKyc = res;
				if (res) {
					fillForm(res);
					detailsConfirmed = true;
					// a previous submission only exists because a card was added and
					// saved first (see handleSubmit), so this is safe to assume
					cardAdded = true;
					currentStep = 2;
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

		if (!fullName.trim()) {
			errors.full_name = 'Full name is required';
		}

		if (businessType === 'company' && !businessName.trim()) {
			errors.business_name = 'Business name is required for companies';
		}

		if (!country) {
			errors.country = 'Country is required';
		}

		if (!address.trim()) {
			errors.address = 'Address is required';
		}

		if (!phone.trim()) {
			errors.phone = 'Phone number is required';
		} else if (!/^\+?[0-9 ()-]+$/.test(phone.trim())) {
			errors.phone = 'Enter a valid phone number';
		}

		if (!website.trim()) {
			errors.website = 'Website is required';
		} else if (!/^https?:\/\/.+/i.test(website.trim())) {
			errors.website = 'Enter a valid URL starting with http:// or https://';
		}

		return Object.keys(errors).length === 0;
	}

	// step 1: just validates and moves on to the payment step. Nothing is saved yet -
	// the KYC is only submitted once the payment step is also done (see handleSubmit).
	function handleNext() {
		if (!validate()) {
			return;
		}

		detailsConfirmed = true;
		currentStep = 2;
	}

	// step 2: this is where the KYC actually gets submitted, now that the card is added too.
	function handleSubmit() {
		if (saving || !cardAdded) {
			return;
		}

		saving = true;

		submitKyc({
			full_name: fullName.trim(),
			business_type: businessType,
			business_name: businessName.trim() || undefined,
			country,
			address: address.trim(),
			phone: phone.trim(),
			website: website.trim()
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

	// called once the card component (embedded from core) confirms the card was
	// added and verified - we then submit the KYC automatically
	function handleCardAdded() {
		cardAdded = true;
		toast.success('Card added.');
		handleSubmit();
	}

	function handleCardError(message: string) {
		toast.error(message);
	}
</script>

<svelte:head>
	<title>KYC Verification | Hyvor Relay</title>
</svelte:head>

<SingleBox>
	<div class="top">
		<h1>KYC Verification</h1>
		<p class="subtitle">
			We need a few details about you or your business, and your payment details, to comply
			with regulations before you can send emails on Hyvor Relay Cloud.
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
							Your KYC submission is under review. You can still update the details below
							and resubmit while it's pending.
						</Callout>
					</div>
				{:else if existingKyc?.status === 'approved'}
					<div class="callout-wrap">
						<Callout type="success">Your KYC has been approved.</Callout>
					</div>
				{:else if existingKyc?.status === 'rejected'}
					<div class="callout-wrap">
						<Callout type="danger">
							Your KYC submission was rejected. Please review the details below and
							resubmit.
						</Callout>
					</div>
				{/if}

				<div class="form">
					<SplitControl label="Full name" caption="Your full legal name">
						<FormControl>
							<TextInput
								bind:value={fullName}
								block
								disabled={isApproved}
								placeholder="Jane Doe"
							/>
							{#if errors.full_name}
								<Validation state="error">{errors.full_name}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl
						label="Account type"
						caption="Are you sending emails as an individual or a business?"
					>
						<FormControl>
							<Select
								bind:value={businessType}
								options={businessTypeOptions}
								block
								disabled={isApproved}
							/>
						</FormControl>
					</SplitControl>

					{#if businessType === 'company'}
						<SplitControl
							label="Business name"
							caption="Your company or organization's legal name"
						>
							<FormControl>
								<TextInput
									bind:value={businessName}
									block
									disabled={isApproved}
									placeholder="Acme Inc."
								/>
								{#if errors.business_name}
									<Validation state="error">{errors.business_name}</Validation>
								{/if}
							</FormControl>
						</SplitControl>
					{/if}

					<SplitControl label="Country" caption="Country of residence or incorporation">
						<FormControl>
							<Select
								bind:value={country}
								options={countryOptions}
								placeholder="Select a country"
								block
								disabled={isApproved}
								state={errors.country ? 'error' : 'default'}
							/>
							{#if errors.country}
								<Validation state="error">{errors.country}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Address" caption="Your residential or business address">
						<FormControl>
							<Textarea
								bind:value={address}
								block
								rows={3}
								disabled={isApproved}
								placeholder="123 Main Street, City, Postal Code"
							/>
							{#if errors.address}
								<Validation state="error">{errors.address}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Phone number" caption="A phone number we can reach you on">
						<FormControl>
							<TextInput
								bind:value={phone}
								block
								disabled={isApproved}
								placeholder="+1 234 567 8900"
							/>
							{#if errors.phone}
								<Validation state="error">{errors.phone}</Validation>
							{/if}
						</FormControl>
					</SplitControl>

					<SplitControl label="Website" caption="A website related to your use case">
						<FormControl>
							<TextInput
								bind:value={website}
								block
								disabled={isApproved}
								placeholder="https://example.com"
							/>
							{#if errors.website}
								<Validation state="error">{errors.website}</Validation>
							{/if}
						</FormControl>
					</SplitControl>
				</div>

				{#if !isApproved}
					<div class="actions">
						<Button color="accent" variant="fill" on:click={handleNext}>Next</Button>
					</div>
				{/if}
			{:else}
				{#if existingKyc?.status === 'rejected'}
					<div class="callout-wrap">
						<Callout type="danger">
							Your KYC submission was rejected. Go back to Step 1 to update your details,
							then submit again below.
						</Callout>
					</div>
				{:else if existingKyc?.status === 'approved'}
					<div class="callout-wrap">
						<Callout type="success">
							Your KYC has been approved. Your card will be charged automatically for
							your subscription.
						</Callout>
					</div>
				{:else if existingKyc?.status === 'pending'}
					<div class="callout-wrap">
						<Callout type="info">
							Your KYC submission is under review. You can still update your payment
							details below.
						</Callout>
					</div>
				{:else}
					<div class="callout-wrap">
						<Callout type="info">
							Add your payment details below, then submit to complete your KYC
							verification. Your card will be charged automatically once it's approved.
						</Callout>
					</div>
				{/if}

				{#if !isApproved}
					<div class="payment-box">
						<CardCollector onSuccess={handleCardAdded} onError={handleCardError} />
					</div>
				{/if}

				<div class="actions space-between">
					<Button variant="outline" color="gray" on:click={() => goToStep(1)}>Back</Button>
					{#if !isApproved}
						<div class="submit-wrap">
							<Tooltip text="Save your card to proceed" disabled={cardAdded}>
								<Button
									color="accent"
									variant="fill"
									disabled={saving || !cardAdded}
									on:click={handleSubmit}
								>
									{saving ? 'Submitting...' : existingKyc ? 'Resubmit KYC' : 'Submit KYC'}
								</Button>
							</Tooltip>
							<!-- {#if !cardAdded}
								<p class="submit-hint">Add and save your card above first.</p>
							{/if} -->
						</div>
					{/if}
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

	.payment-box {
		/* border: 1px solid var(--border);
		border-radius: 8px; */
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

	.submit-hint {
		margin: 6px 0 0;
		font-size: 12px;
		color: var(--text-light);
	}
</style>
