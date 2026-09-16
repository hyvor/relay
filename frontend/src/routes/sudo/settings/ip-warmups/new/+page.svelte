<script lang="ts">
	import { page } from '$app/state';
	import { goto } from '$app/navigation';
	import { Button, IconMessage, Select, TextInput, toast } from '@hyvor/design/components';
	import IconCaretLeft from '@hyvor/icons/IconCaretLeft';
	import SingleBox from '../../../SingleBox.svelte';
	import { createWarmupSchedule } from '../../../sudoActions';
	import { ipAddressesStore, sudoConfigStore, warmupSchedulesStore } from '../../../sudoStore';

	let saving = $state(false);

	let ipParam = $derived(page.url.searchParams.get('ip'));
	let lockedIp = $derived(
		ipParam ? ($ipAddressesStore.find((ip) => ip.ip_address === ipParam) ?? null) : null
	);

	let selectableIps = $derived(
		$ipAddressesStore.filter((ip) => ip.current_warmup_schedule?.status !== 'warming')
	);
	let selectedIpId = $state('');

	let schedule = $state<number[]>(Array(30).fill(0));

	function applyDefaultSchedule() {
		schedule = [...$sudoConfigStore.default_warmup_schedule];
	}

	function handleInputChange(index: number, value: string) {
		const numValue = parseInt(value, 10);
		if (isNaN(numValue) || numValue < 0) return;

		const newSchedule = [...schedule];
		newSchedule[index] = numValue;

		// Auto-fill all subsequent days to be at least this value
		for (let i = index + 1; i < 30; i++) {
			if (newSchedule[i] < numValue) {
				newSchedule[i] = numValue;
			}
		}

		schedule = newSchedule;
	}

	async function handleSave() {
		const ipAddressId = lockedIp ? lockedIp.id : Number(selectedIpId);

		if (!ipAddressId) {
			toast.error('Please select an IP address');
			return;
		}

		for (let i = 1; i < 30; i++) {
			if (schedule[i] < schedule[i - 1]) {
				toast.error(`Day ${i + 1} value cannot be less than Day ${i} value.`);
				return;
			}
		}

		saving = true;
		try {
			const created = await createWarmupSchedule(ipAddressId, schedule);

			warmupSchedulesStore.update((schedules) => [created, ...schedules]);
			ipAddressesStore.update((ips) =>
				ips.map((ip) =>
					ip.id === ipAddressId ? { ...ip, current_warmup_schedule: created } : ip
				)
			);

			toast.success('Warmup schedule started');
			goto('/sudo/settings/ip-warmups');
		} catch (error: any) {
			toast.error('Failed to start warmup: ' + error.message);
		} finally {
			saving = false;
		}
	}
</script>

<SingleBox>
	<div class="header">
		<Button size="small" color="input" as="a" href="/sudo/settings/ip-warmups">
			{#snippet start()}
				<IconCaretLeft size={12} />
			{/snippet}
			All Warmups
		</Button>
		<div class="title">New Warmup Schedule</div>
	</div>

	<div class="content">
		{#if ipParam && !lockedIp}
			<IconMessage error message={`IP address ${ipParam} was not found.`} />
		{:else}
			<div class="field">
				<div class="label">IP Address</div>
				{#if lockedIp}
					<div class="locked-ip">{lockedIp.ip_address}</div>
					{#if lockedIp.current_warmup_schedule?.status === 'warming'}
						<div class="warning">
							This IP address already has an active warmup schedule.
						</div>
					{/if}
				{:else}
					<Select
						bind:value={selectedIpId}
						placeholder="Select an IP address"
						block
						options={selectableIps.map((ip) => ({
							value: String(ip.id),
							label: ip.ip_address
						}))}
					/>
				{/if}
			</div>

			<div class="actions">
				<Button
					size="small"
					color="input"
					variant="outline"
					onclick={applyDefaultSchedule}
					disabled={saving}
				>
					Use Default Schedule
				</Button>
			</div>

			<div class="schedule-grid">
				{#each schedule as value, i (i)}
					<div class="day-input">
						<label class="day-label" for="day-{i}">Day {i + 1}</label>
						<TextInput
							id="day-{i}"
							type="number"
							{value}
							on:input={(e) => handleInputChange(i, e.currentTarget.value)}
							block
							disabled={saving}
							min="0"
						/>
					</div>
				{/each}
			</div>

			<div class="save">
				<Button
					onclick={handleSave}
					loading={saving}
					disabled={saving || lockedIp?.current_warmup_schedule?.status === 'warming'}
				>
					Start Warmup
				</Button>
			</div>
		{/if}
	</div>
</SingleBox>

<style>
	.header {
		display: flex;
		align-items: center;
		gap: 15px;
		padding: 20px 25px;
		border-bottom: 1px solid var(--border);
	}
	.title {
		font-weight: 600;
		font-size: 16px;
	}
	.content {
		padding: 25px;
		max-width: 700px;
	}
	.field {
		margin-bottom: 20px;
	}
	.label {
		font-size: 13px;
		color: var(--text-light);
		font-weight: 500;
		margin-bottom: 6px;
	}
	.locked-ip {
		font-size: 16px;
		font-weight: 600;
	}
	.warning {
		margin-top: 8px;
		color: var(--red);
		font-size: 13px;
	}
	.actions {
		margin-bottom: 20px;
	}
	.schedule-grid {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		gap: 12px;
	}
	.day-input {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
	.day-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: 500;
	}
	.save {
		margin-top: 25px;
	}

	@media (max-width: 900px) {
		.schedule-grid {
			grid-template-columns: repeat(3, 1fr);
		}
	}

	@media (max-width: 600px) {
		.schedule-grid {
			grid-template-columns: repeat(2, 1fr);
		}
	}
</style>
