<script lang="ts">
	import { onMount } from 'svelte';
	import {
		TextInput,
		IconButton,
		toast,
		confirm,
		ActionList,
		ActionListItem,
		Button,
		Loader,
		LoadButton
	} from '@hyvor/design/components';
	import IconX from '@hyvor/icons/IconX';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import SuppressionList from './SuppressionList.svelte';
	import Selector from '../../@components/content/Selector.svelte';
	import type { Suppression, SuppressionReason } from '../../types';
	import { getSuppressions, deleteSuppression } from '../../lib/actions/suppressionActions';
	import { redirectIfCant } from '../../lib/scope.svelte';

	let suppressions: Suppression[] = $state([]);
	let loading = $state(true);
	let loadingMore = $state(false);
	let emailSearchVal = $state('');
	let emailSearch = $state('');
	let reason: SuppressionReason | null = $state(null);
	let reasonKey = $derived.by(() => {
		if (reason === 'bounce') return 'Bounce';
		if (reason === 'complaint') return 'Complaint';
		return 'All';
	});
	let showReason = $state(false);
	let hasMore = $state(true);

	const LIMIT = 50;
	let offset = $state(0);

	onMount(() => {
		loadSuppressions();
	});

	function loadSuppressions(reset = false) {
		if (reset) {
			offset = 0;
			suppressions = [];
			hasMore = true;
		}

		const currentOffset = reset ? 0 : offset;
		const isInitialLoad = currentOffset === 0;

		if (isInitialLoad) {
			loading = true;
		} else {
			loadingMore = true;
		}

		const search = emailSearch === '' ? null : emailSearch;

		getSuppressions(search, reason, LIMIT, currentOffset)
			.then((newSuppressions) => {
				if (reset) {
					suppressions = newSuppressions;
				} else {
					suppressions = [...suppressions, ...newSuppressions];
				}

				hasMore = newSuppressions.length === LIMIT;
				offset = currentOffset + newSuppressions.length;
			})
			.catch((error) => {
				toast.error('Failed to load suppressions: ' + error.message);
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	function selectReason(r: SuppressionReason | null) {
		showReason = false;
		reason = r;
		loadSuppressions(true);
	}

	function handleLoadMore() {
		if (!loadingMore && hasMore) {
			loadSuppressions();
		}
	}

	async function handleDeleteSuppression(suppression: Suppression) {
		const confirmed = await confirm({
			title: 'Remove suppression',
			content: `Are you sure you want to remove the suppression for "${suppression.email}"? We advise you to only remove suppressions if you are sure that the email is not a spam trap or a hard bounce.`,
			confirmText: 'Remove',
			cancelText: 'Cancel',
			danger: true
		});

		if (confirmed) {
			deleteSuppression(suppression.id)
				.then(() => {
					suppressions = suppressions.filter((s) => s.id !== suppression.id);
					toast.success('Suppression removed');
				})
				.catch((error) => {
					console.error('Failed to delete suppression:', error);
					toast.error('Failed to remove suppression');
				});
		}
	}

	const searchActions = {
		onKeydown: (e: KeyboardEvent) => {
			if (e.key === 'Enter') {
				emailSearch = emailSearchVal.trim();
				loadSuppressions(true);
			}
		},
		onBlur: () => {
			if (emailSearch !== emailSearchVal) {
				emailSearch = emailSearchVal.trim();
				loadSuppressions(true);
			}
		},
		onClear: () => {
			emailSearchVal = '';
			emailSearch = '';
			loadSuppressions(true);
		}
	};

	onMount(() => redirectIfCant('suppressions.read'));
</script>

<SingleBox>
	<div class="top">
		<div class="left">
			<Selector name="Reason" bind:show={showReason} value={reasonKey} width={200}>
				<ActionList selection="single" selectionAlign="end">
					<ActionListItem on:click={() => selectReason(null)} selected={reason === null}>
						All
					</ActionListItem>
					<ActionListItem
						on:click={() => selectReason('bounce')}
						selected={reason === 'bounce'}
					>
						Bounce
					</ActionListItem>
					<ActionListItem
						on:click={() => selectReason('complaint')}
						selected={reason === 'complaint'}
					>
						Complaint
					</ActionListItem>
				</ActionList>
			</Selector>

			<div class="search-wrap">
				<TextInput
					bind:value={emailSearchVal}
					placeholder="Search by email"
					on:keydown={searchActions.onKeydown}
					on:blur={searchActions.onBlur}
					size="small"
				>
					{#snippet end()}
						{#if emailSearchVal.trim() !== ''}
							<IconButton
								variant="invisible"
								color="gray"
								size={16}
								on:click={searchActions.onClear}
							>
								<IconX size={12} />
							</IconButton>
						{/if}
					{/snippet}
				</TextInput>

				{#if emailSearch !== emailSearchVal}
					<span class="press-enter"> ⏎ </span>
				{/if}
			</div>
		</div>
	</div>

	<div class="content">
		{#if loading}
			<div class="loader-container">
				<Loader />
			</div>
		{:else}
			<SuppressionList {suppressions} loading={false} onDelete={handleDeleteSuppression} />

			<div class=load-more>
				<LoadButton
					text="Load More"
					loading={loadingMore}
					show={hasMore && !loading && suppressions.length > 0}
					on:click={handleLoadMore}
				/>
			</div>
		{/if}
	</div>
</SingleBox>

<style>
	.top {
		display: flex;
		flex-direction: column;
		gap: 15px;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}

	.left {
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

	.content {
		padding: 30px;
		flex: 1;
		display: flex;
		flex-direction: column;
		overflow: auto;
	}

	.loader-container {
		display: flex;
		justify-content: center;
		align-items: center;
		flex: 1;
	}
	.load-more {
		margin-top: 5px;
	}
</style>
