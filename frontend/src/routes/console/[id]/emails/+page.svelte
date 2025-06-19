<script lang="ts">
	import {
		Button,
		ButtonGroup,
		ActionList,
		ActionListItem,
		TextInput,
		IconButton
	} from '@hyvor/design/components';
	import IconBoxArrowInDown from '@hyvor/icons/IconBoxArrowInDown';
	import IconPlus from '@hyvor/icons/IconPlus';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import type { Email, EmailStatus } from '../../types';
	import { emailStore } from '../../lib/stores/projectStore';
	import Selector from '../../@components/content/Selector.svelte';
	import IconX from '@hyvor/icons/IconX';
	import EmailList from './EmailList.svelte';

	let key = $state(1); // for re-rendering
	let status: EmailStatus | null = $state(null);
	let statusKey = $derived.by(() =>
		status ? status.charAt(0).toUpperCase() + status.slice(1) : 'All'
	);

	let showStatus = $state(false);
	let showList = $state(false);

	let currentEmail: Email | null = $state(null);

	let fromSearchVal: string = $state('');
	let fromSearch: string = $state('');
	let toSearchVal: string = $state('');
	let toSearch: string = $state('');

	function selectStatus(s: EmailStatus | null) {
		showStatus = false;
		status = s;
	}

	const createSearchActions = (type: 'from' | 'to') => ({
		onKeydown: (e: KeyboardEvent) => {
			if (e.key === 'Enter') {
				if (type === 'from') {
					fromSearch = fromSearchVal.trim();
				} else {
					toSearch = toSearchVal.trim();
				}
			}
		},
		onBlur: () => {
			if (type === 'from' && fromSearch !== fromSearchVal) {
				fromSearch = fromSearchVal.trim();
			} else if (type === 'to' && toSearch !== toSearchVal) {
				toSearch = toSearchVal.trim();
			}
		},
		onClear: () => {
			if (type === 'from') {
				fromSearchVal = '';
				fromSearch = '';
			} else {
				toSearchVal = '';
				toSearch = '';
			}
		}
	});

	const fromSearchActions = createSearchActions('from');
	const toSearchActions = createSearchActions('to');
</script>

<SingleBox>
	<div class="top">
		<div class="left">
			<Selector
				name="Status"
				bind:show={showStatus}
				value={statusKey}
				width={200}
			>
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
						on:click={() => selectStatus('sent')}
						selected={status === 'sent'}
					>
						Sent
					</ActionListItem>
					<ActionListItem
						on:click={() => selectStatus('failed')}
						selected={status === 'failed'}
					>
						Failed
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
			</div>
		</div>
	</div>

	<EmailList
		{status}
		{key}
		from_search={fromSearch === '' ? null : fromSearch}
		to_search={toSearch === '' ? null : toSearch}
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
		gap: 20px;
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
