<script lang="ts">
	import {
		Button,
		Dropdown,
		IconButton,
		IconMessage,
		LoadButton,
		Loader
	} from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconSortDown from '@hyvor/icons/IconSortDown';
	import IconSortUp from '@hyvor/icons/IconSortUp';
	import SingleBox from '../SingleBox.svelte';
	import KycRow from './KycRow.svelte';
	import { getKycs } from '../sudoActions';
	import type { KycSortBy, KycStatus, Organization, SudoKyc } from '../sudoTypes';

	const PER_PAGE = 30;

	const statusOptions = [
		{ value: '', label: 'All' },
		{ value: 'pending', label: 'Pending' },
		{ value: 'approved', label: 'Approved' },
		{ value: 'rejected', label: 'Rejected' }
	];

	const sortByOptions: { value: KycSortBy; label: string }[] = [
		{ value: 'submitted_at', label: 'Submitted date' },
		{ value: 'status', label: 'Status' },
		{ value: 'created_at', label: 'Created date' }
	];

	let statusFilter = $state('');
	let sortBy = $state<KycSortBy>('submitted_at');
	let sortDirection = $state<'asc' | 'desc'>('desc');

	let showStatusDropdown = $state(false);
	let showSortDropdown = $state(false);

	const statusLabel = $derived(
		statusOptions.find((option) => option.value === statusFilter)?.label ?? 'All statuses'
	);
	const sortByLabel = $derived(
		sortByOptions.find((option) => option.value === sortBy)?.label ?? ''
	);

	function selectStatus(value: string) {
		statusFilter = value;
		showStatusDropdown = false;
	}

	function selectSortBy(value: KycSortBy) {
		sortBy = value;
		showSortDropdown = false;
	}

	let kycs: SudoKyc[] = $state([]);
	let orgsMap: Map<number, Organization> = $state(new Map());
	let total = $state(0);
	let loading = $state(true);
	let loadingMore = $state(false);
	let error: string | null = $state(null);

	const hasMore = $derived(kycs.length < total);

	function load(more = false) {
		if (more) {
			loadingMore = true;
		} else {
			loading = true;
		}

		const offset = more ? kycs.length : 0;

		getKycs({
			status: (statusFilter || null) as KycStatus | null,
			sort_by: sortBy,
			sort: sortDirection,
			limit: PER_PAGE,
			offset
		})
			.then((res) => {
				kycs = more ? [...kycs, ...res.kycs] : res.kycs;

				const newMap = more ? new Map(orgsMap) : new Map<number, Organization>();
				for (const org of res.orgs) {
					newMap.set(org.id, org);
				}
				orgsMap = newMap;

				total = res.total;
				error = null;
			})
			.catch((e) => {
				error = e.message;
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	// load() reads statusFilter + sortBy + sortDirection, so this re-runs (from
	// the first page) whenever the filter/sort controls change.
	$effect(() => {
		load();
	});

	function toggleDirection() {
		sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
	}

	function handleUpdate(updated: SudoKyc) {
		kycs = kycs.map((kyc) => (kyc.id === updated.id ? updated : kyc));
	}
</script>

<SingleBox>
	<div class="top">
		<div class="filters">
			<Dropdown bind:show={showStatusDropdown} width={180}>
				{#snippet trigger()}
					<Button size="small" color="input">
						<span class="name">Status</span>
						<span class="val">{statusLabel}</span>
						{#snippet end()}
							<IconCaretDown size={12} />
						{/snippet}
					</Button>
				{/snippet}
				{#snippet content()}
					<div class="options">
						{#each statusOptions as option (option.value)}
							<button
								class="option"
								class:active={option.value === statusFilter}
								onclick={() => selectStatus(option.value)}
							>
								{option.label}
							</button>
						{/each}
					</div>
				{/snippet}
			</Dropdown>

			<Dropdown bind:show={showSortDropdown} width={180}>
				{#snippet trigger()}
					<Button size="small" color="input">
						<span class="name">Sort by</span>
						<span class="val">{sortByLabel}</span>
						{#snippet end()}
							<IconCaretDown size={12} />
						{/snippet}
					</Button>
				{/snippet}
				{#snippet content()}
					<div class="options">
						{#each sortByOptions as option (option.value)}
							<button
								class="option"
								class:active={option.value === sortBy}
								onclick={() => selectSortBy(option.value)}
							>
								{option.label}
							</button>
						{/each}
					</div>
				{/snippet}
			</Dropdown>
			<IconButton
				size={25}
				color="input"
				variant="outline"
				aria-label="Toggle sort direction"
				on:click={toggleDirection}
			>
				{#if sortDirection === 'asc'}
					<IconSortUp size={14} />
				{:else}
					<IconSortDown size={14} />
				{/if}
			</IconButton>
		</div>
	</div>

	{#if loading}
		<Loader full />
	{:else if error}
		<IconMessage error message={error} />
	{:else if kycs.length === 0}
		<IconMessage empty message="No KYC submissions found" />
	{:else}
		<div class="list">
			<div class="header">
				<div>Organization</div>
				<div>Applicant</div>
				<div>Country</div>
				<div>Status</div>
				<div>Submitted</div>
				<div></div>
			</div>

			{#each kycs as kyc (kyc.id)}
				<KycRow
					{kyc}
					org={orgsMap.get(kyc.organization_id) ?? null}
					onUpdate={handleUpdate}
				/>
			{/each}

			<LoadButton
				text="Load More"
				loading={loadingMore}
				show={hasMore}
				on:click={() => load(true)}
			/>
		</div>
	{/if}
</SingleBox>

<style>
	.top {
		display: flex;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}
	.filters {
		flex: 1;
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		align-items: center;
	}
	.name {
		margin-right: 6px;
	}
	.val {
		font-weight: normal;
		font-size: 13px;
	}
	.options {
		display: flex;
		flex-direction: column;
	}
	.option {
		display: block;
		width: 100%;
		text-align: left;
		padding: 8px 12px;
		border-radius: 6px;
		background: transparent;
		border: none;
		cursor: pointer;
		font-size: 14px;
	}
	.option:hover {
		background: var(--hover);
	}
	.option.active {
		font-weight: 600;
		color: var(--accent);
	}

	.list {
		flex: 1;
		overflow: auto;
		padding: 20px 0;
	}
	.header {
		display: grid;
		grid-template-columns: 1.5fr 2fr 1fr 1fr 1fr 2fr;
		font-size: 14px;
		font-weight: 600;
		color: var(--text-light);
		gap: 15px;
		padding: 5px 30px 15px;
	}
</style>
