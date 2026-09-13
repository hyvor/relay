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
		toast
	} from '@hyvor/design/components';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';
	import { getKyc, submitKyc } from '../../lib/actions/kycActions';
	import { COUNTRIES } from '../../lib/countries';
	import type { Kyc, KycBusinessType } from '../../types';

	let loading = $state(true);
	let saving = $state(false);
	let existingKyc = $state<Kyc | null>(null);

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
		website = kyc.website ?? '';
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

		if (website.trim() && !/^https?:\/\/.+/i.test(website.trim())) {
			errors.website = 'Enter a valid URL starting with http:// or https://';
		}

		return Object.keys(errors).length === 0;
	}

	function handleSubmit() {
		if (!validate()) {
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
			website: website.trim() || undefined
		})
			.then((res) => {
				existingKyc = res;
				toast.success("KYC submitted. We'll review your details shortly.");
			})
			.catch((error) => {
				toast.error(error.message ?? 'Failed to submit KYC');
			})
			.finally(() => {
				saving = false;
			});
	}
</script>

<svelte:head>
	<title>KYC Verification | Hyvor Relay</title>
</svelte:head>

<SingleBox>
	<div class="top">
		<h1>KYC Verification</h1>
		<p class="subtitle">
			We need a few details about you or your business to comply with regulations before you
			can send emails on Hyvor Relay Cloud.
		</p>
	</div>

	<div class="body">
		{#if loading}
			<div class="loader-wrap">
				<Loader size="large" />
			</div>
		{:else}
			{#if existingKyc?.status === 'pending'}
				<div class="callout-wrap">
					<Callout type="info">
						Your KYC submission is under review. You can still update the details below and
						resubmit while it's pending.
					</Callout>
				</div>
			{:else if existingKyc?.status === 'approved'}
				<div class="callout-wrap">
					<Callout type="success">Your KYC has been approved.</Callout>
				</div>
			{:else if existingKyc?.status === 'rejected'}
				<div class="callout-wrap">
					<Callout type="danger">
						Your KYC submission was rejected. Please review the details below and resubmit.
					</Callout>
				</div>
			{/if}

			<div class="form">
				<SplitControl label="Full name" caption="Your full legal name">
					<FormControl>
						<TextInput
							bind:value={fullName}
							block
							disabled={saving || isApproved}
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
							disabled={saving || isApproved}
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
								disabled={saving || isApproved}
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
							disabled={saving || isApproved}
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
							disabled={saving || isApproved}
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
							disabled={saving || isApproved}
							placeholder="+1 234 567 8900"
						/>
						{#if errors.phone}
							<Validation state="error">{errors.phone}</Validation>
						{/if}
					</FormControl>
				</SplitControl>

				<SplitControl label="Website" caption="Optional. A website related to your use case">
					<FormControl>
						<TextInput
							bind:value={website}
							block
							disabled={saving || isApproved}
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
					<Button color="accent" variant="fill" disabled={saving} on:click={handleSubmit}>
						{saving ? 'Submitting...' : existingKyc ? 'Resubmit KYC' : 'Submit KYC'}
					</Button>
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

	.callout-wrap {
		margin-bottom: 20px;
	}

	.actions {
		max-width: 720px;
		display: flex;
		justify-content: flex-end;
		margin-top: 20px;
	}
</style>
