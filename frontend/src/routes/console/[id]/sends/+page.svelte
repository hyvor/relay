<script lang="ts">
	import { ActionList, ActionListItem, TextInput, IconButton } from '@hyvor/design/components';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import type { Send, SendRecipientStatus } from '../../types';
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

	let currentEmail: Send | null = $state(null);

	let fromSearchVal: string = $state('');
	let fromSearch: string = $state('');
	let toSearchVal: string = $state('');
	let toSearch: string = $state('');
	let subjectSearchVal: string = $state('');
	let subjectSearch: string = $state('');

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
					placeholder="To address"
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
</style>
