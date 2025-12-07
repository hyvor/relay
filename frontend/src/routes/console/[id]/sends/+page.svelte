<script lang="ts">
	import { ActionList, ActionListItem, TextInput, IconButton } from '@hyvor/design/components';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import type { DateFilterPreset, Send, SendRecipientStatus } from '../../types';
	import Selector from '../../@components/content/Selector.svelte';
	import IconX from '@hyvor/icons/IconX';
	import SendsList from './SendsList.svelte';

	let key = $state(1); // for re-rendering
	let status: SendRecipientStatus | null = $state(null);
	let statusKey = $derived.by(() =>
		status ? status.charAt(0).toUpperCase() + status.slice(1) : 'All'
	);

	let showStatus = $state(false);
	let showList = $state(false);
	let showDateFilter = $state(false);

	let currentEmail: Send | null = $state(null);

	let fromSearchVal: string = $state('');
	let fromSearch: string = $state('');
	let toSearchVal: string = $state('');
	let toSearch: string = $state('');
	let subjectSearchVal: string = $state('');
	let subjectSearch: string = $state('');

	let dateFilterPreset: DateFilterPreset = $state(null);
	let customDateFrom: string = $state('');
	let customDateTo: string = $state('');

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
		const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // Adjust for Monday start
		const monday = new Date(today);
		monday.setDate(today.getDate() + diff);
		monday.setHours(0, 0, 0, 0);
		return monday;
	}

	let dateFromSearch = $derived.by(() => {
		if (dateFilterPreset === 'today') {
			return formatDate(getToday());
		} else if (dateFilterPreset === 'yesterday') {
			return formatDate(getYesterday());
		} else if (dateFilterPreset === 'this_week') {
			return formatDate(getStartOfWeek());
		} else if (dateFilterPreset === 'custom' && customDateFrom) {
			return customDateFrom + ' 00:00:00';
		}
		return null;
	});

	let dateToSearch = $derived.by(() => {
		if (dateFilterPreset === 'today') {
			return formatDate(getToday(), true);
		} else if (dateFilterPreset === 'yesterday') {
			return formatDate(getYesterday(), true);
		} else if (dateFilterPreset === 'this_week') {
			return formatDate(getToday(), true);
		} else if (dateFilterPreset === 'custom' && customDateTo) {
			return customDateTo + ' 23:59:59';
		}
		return null;
	});

	let minCustomDate = $derived.by(() => {
		const minDate = new Date();
		minDate.setDate(minDate.getDate() - 30);
		return formatDateOnly(minDate);
	});

	let maxCustomDate = $derived.by(() => formatDateOnly(getToday()));

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

	function selectDateFilter(preset: DateFilterPreset) {
		dateFilterPreset = preset;
		if (preset !== 'custom') {
			showDateFilter = false;
			customDateFrom = '';
			customDateTo = '';
		}
	}

	function selectStatus(s: SendRecipientStatus | null) {
		showStatus = false;
		status = s;
	}

	const createSearchActions = (type: 'from' | 'to' | 'subject') => ({
		onKeydown: (e: KeyboardEvent) => {
			if (e.key === 'Enter') {
				if (type === 'from') {
					fromSearch = fromSearchVal.trim();
				} else if (type === 'to') {
					toSearch = toSearchVal.trim();
				} else {
					subjectSearch = subjectSearchVal.trim();
				}
			}
		},
		onBlur: () => {
			if (type === 'from' && fromSearch !== fromSearchVal) {
				fromSearch = fromSearchVal.trim();
			} else if (type === 'to' && toSearch !== toSearchVal) {
				toSearch = toSearchVal.trim();
			} else if (type === 'subject' && subjectSearch !== subjectSearchVal) {
				subjectSearch = subjectSearchVal.trim();
			}
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

	const fromSearchActions = createSearchActions('from');
	const toSearchActions = createSearchActions('to');
	const subjectSearchActions = createSearchActions('subject');
</script>

<SingleBox>
	<div class="top">
		<div class="left">
			<Selector name="Status" bind:show={showStatus} value={statusKey} width={200}>
				<ActionList selection="single" selectionAlign="end">
					<ActionListItem on:click={() => selectStatus(null)} selected={status === null}>
						All
					</ActionListItem>
					<ActionListItem
						on:click={() => selectStatus('queued')}
						selected={status === 'queued'}
					>
						Queued
					</ActionListItem>
					<ActionListItem
						on:click={() => selectStatus('accepted')}
						selected={status === 'accepted'}
					>
						Accepted
					</ActionListItem>
					<ActionListItem
						on:click={() => selectStatus('bounced')}
						selected={status === 'bounced'}
					>
						Bounced
					</ActionListItem>
				</ActionList>
			</Selector>

			<Selector name="Date" bind:show={showDateFilter} value={dateFilterDisplayValue} width={280}>
				<ActionList selection="single" selectionAlign="end">
					<ActionListItem on:click={() => selectDateFilter(null)} selected={dateFilterPreset === null}>
						All
					</ActionListItem>
					<ActionListItem on:click={() => selectDateFilter('today')} selected={dateFilterPreset === 'today'}>
						Today
					</ActionListItem>
					<ActionListItem on:click={() => selectDateFilter('yesterday')} selected={dateFilterPreset === 'yesterday'}>
						Yesterday
					</ActionListItem>
					<ActionListItem on:click={() => selectDateFilter('this_week')} selected={dateFilterPreset === 'this_week'}>
						This week
					</ActionListItem>
					<ActionListItem on:click={() => selectDateFilter('custom')} selected={dateFilterPreset === 'custom'}>
						Custom
					</ActionListItem>
				</ActionList>
				{#if dateFilterPreset === 'custom'}
					<div class="custom-date-inputs">
						<div class="date-input-row">
							<label for="date-from">From</label>
							<TextInput
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
								type="date"
								id="date-to"
								bind:value={customDateTo}
								min={minCustomDate}
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
					style="width:200px"
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
					<span class="press-enter"> ⏎ </span>
				{/if}

				<TextInput
					bind:value={toSearchVal}
					placeholder="Recipient address"
					style="width:200px"
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
					<span class="press-enter"> ⏎ </span>
				{/if}

				<TextInput
					bind:value={subjectSearchVal}
					placeholder="Subject"
					style="width:200px"
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
					<span class="press-enter"> ⏎ </span>
				{/if}
			</div>
		</div>
	</div>

	<SendsList
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
	.left {
		flex: 1;
		display: flex;
		gap: 10px;
		align-items: center;
	}
	.search-wrap {
		display: flex;
		gap: 10px;
		align-items: center;

		.press-enter {
			color: var(--text-light);
			font-size: 14px;
			margin-left: 4px;
		}
		:global(input) {
			font-size: 14px;
		}
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
