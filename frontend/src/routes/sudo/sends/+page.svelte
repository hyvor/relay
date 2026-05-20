<script lang="ts">
	import {
		ActionList,
		ActionListItem,
		TextInput,
		IconButton
	} from '@hyvor/design/components';
	import IconX from '@hyvor/icons/IconX';
	import SingleBox from '../SingleBox.svelte';
	import Selector from '../../console/@components/content/Selector.svelte';
	import type {
		SudoProject,
		SudoSendRecipientStatus,
		SudoSendsDateFilterPreset
	} from '../sudoTypes';
	import SendsList from './SendsList.svelte';
	import ProjectSelectDropdown from './ProjectSelectDropdown.svelte';

	const STATUS_OPTIONS: { value: SudoSendRecipientStatus | null; label: string }[] = [
		{ value: null, label: 'All' },
		{ value: 'queued', label: 'Queued' },
		{ value: 'accepted', label: 'Accepted' },
		{ value: 'deferred', label: 'Deferred' },
		{ value: 'bounced', label: 'Bounced' },
		{ value: 'complained', label: 'Complained' },
		{ value: 'suppressed', label: 'Suppressed' },
		{ value: 'failed', label: 'Failed' }
	];

	let key = $state(1);

	let selectedProject: SudoProject | null = $state(null);

	let status: SudoSendRecipientStatus | null = $state(null);
	let showStatus = $state(false);
	let statusLabel = $derived(
		STATUS_OPTIONS.find((opt) => opt.value === status)?.label ?? 'All'
	);

	let showDateFilter = $state(false);
	let dateFilterPreset: SudoSendsDateFilterPreset = $state(null);
	let customDateFrom: string = $state('');
	let customDateTo: string = $state('');

	let fromSearchVal: string = $state('');
	let fromSearch: string = $state('');
	let toSearchVal: string = $state('');
	let toSearch: string = $state('');
	let subjectSearchVal: string = $state('');
	let subjectSearch: string = $state('');

	function formatDateOnly(date: Date): string {
		return date.toISOString().split('T')[0];
	}

	function formatDate(date: Date, endOfDay = false): string {
		const datePart = formatDateOnly(date);
		return endOfDay ? `${datePart} 23:59:59` : `${datePart} 00:00:00`;
	}

	function getToday(): Date {
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		return today;
	}

	function getYesterday(): Date {
		const yesterday = new Date();
		yesterday.setDate(yesterday.getDate() - 1);
		yesterday.setHours(0, 0, 0, 0);
		return yesterday;
	}

	function getStartOfWeek(): Date {
		const today = new Date();
		const dayOfWeek = today.getDay();
		const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
		const monday = new Date(today);
		monday.setDate(today.getDate() + diff);
		monday.setHours(0, 0, 0, 0);
		return monday;
	}

	let dateFromSearch = $derived.by(() => {
		if (dateFilterPreset === 'today') return formatDate(getToday());
		if (dateFilterPreset === 'yesterday') return formatDate(getYesterday());
		if (dateFilterPreset === 'this_week') return formatDate(getStartOfWeek());
		if (dateFilterPreset === 'custom' && customDateFrom) return customDateFrom + ' 00:00:00';
		return null;
	});

	let dateToSearch = $derived.by(() => {
		if (dateFilterPreset === 'today') return formatDate(getToday(), true);
		if (dateFilterPreset === 'yesterday') return formatDate(getYesterday(), true);
		if (dateFilterPreset === 'this_week') return formatDate(getToday(), true);
		if (dateFilterPreset === 'custom' && customDateTo) return customDateTo + ' 23:59:59';
		return null;
	});

	let minCustomDate = $derived.by(() => {
		const minDate = new Date();
		minDate.setDate(minDate.getDate() - 30);
		return formatDateOnly(minDate);
	});

	let maxCustomDate = $derived(formatDateOnly(getToday()));

	let dateFilterDisplayValue = $derived.by(() => {
		switch (dateFilterPreset) {
			case 'today':
				return 'Today';
			case 'yesterday':
				return 'Yesterday';
			case 'this_week':
				return 'This week';
			case 'custom':
				return 'Custom';
			default:
				return 'All';
		}
	});

	function selectDateFilter(preset: SudoSendsDateFilterPreset) {
		dateFilterPreset = preset;
		if (preset !== 'custom') {
			showDateFilter = false;
			customDateFrom = '';
			customDateTo = '';
		}
	}

	function selectStatus(s: SudoSendRecipientStatus | null) {
		showStatus = false;
		status = s;
	}

	const createSearchActions = (type: 'from' | 'to' | 'subject') => ({
		onKeydown: (e: KeyboardEvent) => {
			if (e.key === 'Enter') applySearch(type);
		},
		onBlur: () => {
			applySearch(type);
		},
		onClear: () => {
			if (type === 'from') {
				fromSearchVal = '';
				fromSearch = '';
			} else if (type === 'to') {
				toSearchVal = '';
				toSearch = '';
			} else {
				subjectSearchVal = '';
				subjectSearch = '';
			}
		}
	});

	function applySearch(type: 'from' | 'to' | 'subject') {
		if (type === 'from' && fromSearch !== fromSearchVal) {
			fromSearch = fromSearchVal.trim();
		} else if (type === 'to' && toSearch !== toSearchVal) {
			toSearch = toSearchVal.trim();
		} else if (type === 'subject' && subjectSearch !== subjectSearchVal) {
			subjectSearch = subjectSearchVal.trim();
		}
	}

	const fromSearchActions = createSearchActions('from');
	const toSearchActions = createSearchActions('to');
	const subjectSearchActions = createSearchActions('subject');
</script>

<SingleBox>
	<div class="top">
		<div class="filters">
			<ProjectSelectDropdown bind:value={selectedProject} />

			<Selector name="Status" bind:show={showStatus} value={statusLabel} width={200}>
				<ActionList selection="single" selectionAlign="end">
					{#each STATUS_OPTIONS as opt}
						<ActionListItem
							on:click={() => selectStatus(opt.value)}
							selected={status === opt.value}
						>
							{opt.label}
						</ActionListItem>
					{/each}
				</ActionList>
			</Selector>

			<Selector
				name="Date"
				bind:show={showDateFilter}
				value={dateFilterDisplayValue}
				width={280}
			>
				<ActionList selection="single" selectionAlign="end">
					<ActionListItem
						on:click={() => selectDateFilter(null)}
						selected={dateFilterPreset === null}
					>
						All
					</ActionListItem>
					<ActionListItem
						on:click={() => selectDateFilter('today')}
						selected={dateFilterPreset === 'today'}
					>
						Today
					</ActionListItem>
					<ActionListItem
						on:click={() => selectDateFilter('yesterday')}
						selected={dateFilterPreset === 'yesterday'}
					>
						Yesterday
					</ActionListItem>
					<ActionListItem
						on:click={() => selectDateFilter('this_week')}
						selected={dateFilterPreset === 'this_week'}
					>
						This week
					</ActionListItem>
					<ActionListItem
						on:click={() => selectDateFilter('custom')}
						selected={dateFilterPreset === 'custom'}
					>
						Custom
					</ActionListItem>
				</ActionList>
				{#if dateFilterPreset === 'custom'}
					<div class="custom-date-inputs">
						<div class="date-input-row">
							<label for="date-from">From</label>
							<TextInput
								block
								type="date"
								id="date-from"
								bind:value={customDateFrom}
								min={minCustomDate}
								max={maxCustomDate}
								size="small"
							/>
						</div>
						<div class="date-input-row">
							<label for="date-to">To</label>
							<TextInput
								block
								type="date"
								id="date-to"
								bind:value={customDateTo}
								min={customDateFrom ? customDateFrom : minCustomDate}
								max={maxCustomDate}
								size="small"
							/>
						</div>
					</div>
				{/if}
			</Selector>

			<div class="search-wrap">
				<TextInput
					bind:value={fromSearchVal}
					placeholder="From address"
					style="width:180px"
					on:keydown={fromSearchActions.onKeydown}
					on:blur={fromSearchActions.onBlur}
					size="small"
				>
					{#snippet end()}
						{#if fromSearchVal.trim() !== ''}
							<IconButton
								variant="invisible"
								color="gray"
								size={16}
								on:click={fromSearchActions.onClear}
							>
								<IconX size={12} />
							</IconButton>
						{/if}
					{/snippet}
				</TextInput>
				{#if fromSearch !== fromSearchVal}
					<span class="press-enter">⏎</span>
				{/if}

				<TextInput
					bind:value={toSearchVal}
					placeholder="Recipient address"
					style="width:180px"
					on:keydown={toSearchActions.onKeydown}
					on:blur={toSearchActions.onBlur}
					size="small"
				>
					{#snippet end()}
						{#if toSearchVal.trim() !== ''}
							<IconButton
								variant="invisible"
								color="gray"
								size={16}
								on:click={toSearchActions.onClear}
							>
								<IconX size={12} />
							</IconButton>
						{/if}
					{/snippet}
				</TextInput>
				{#if toSearch !== toSearchVal}
					<span class="press-enter">⏎</span>
				{/if}

				<TextInput
					bind:value={subjectSearchVal}
					placeholder="Subject"
					style="width:180px"
					on:keydown={subjectSearchActions.onKeydown}
					on:blur={subjectSearchActions.onBlur}
					size="small"
				>
					{#snippet end()}
						{#if subjectSearchVal.trim() !== ''}
							<IconButton
								variant="invisible"
								color="gray"
								size={16}
								on:click={subjectSearchActions.onClear}
							>
								<IconX size={12} />
							</IconButton>
						{/if}
					{/snippet}
				</TextInput>
				{#if subjectSearch !== subjectSearchVal}
					<span class="press-enter">⏎</span>
				{/if}
			</div>
		</div>
	</div>

	<SendsList
		project_id={selectedProject?.id ?? null}
		{status}
		{key}
		from_search={fromSearch === '' ? null : fromSearch}
		to_search={toSearch === '' ? null : toSearch}
		subject_search={subjectSearch === '' ? null : subjectSearch}
		date_from_search={dateFromSearch}
		date_to_search={dateToSearch}
	/>
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
	.search-wrap {
		display: flex;
		gap: 10px;
		align-items: center;
		flex-wrap: wrap;
	}
	.press-enter {
		color: var(--text-light);
		font-size: 14px;
		margin-left: 4px;
	}
	.custom-date-inputs {
		padding: 12px;
		border-top: 1px solid var(--border);
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	.date-input-row {
		display: flex;
		align-items: center;
		gap: 10px;
	}
	.date-input-row label {
		font-size: 13px;
		color: var(--text-light);
		width: 40px;
	}
</style>
