<script lang="ts">
	import { onMount } from 'svelte';
	import {
		TextInput,
		IconButton,
		toast,
		confirm,
		ActionList,
		ActionListItem
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
	let emailSearchVal = $state('');
	let emailSearch = $state('');
	let reason: SuppressionReason | null = $state(null);
	let reasonKey = $derived.by(() => {
		if (reason === 'bounce') return 'Bounce';
		if (reason === 'complaint') return 'Complaint';
		return 'All';
	});
	let showReason = $state(false);

	onMount(() => {
		loadSuppressions();
	});

	function loadSuppressions() {
		loading = true;
		getSuppressions(emailSearch === '' ? null : emailSearch, reason)
			.then((suppressionList) => {
				suppressions = suppressionList;
			})
			.catch((error) => {
				toast.error('Failed to load suppressions: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	function selectReason(r: SuppressionReason | null) {
		showReason = false;
		reason = r;
		loadSuppressions();
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
				loadSuppressions();
			}
		},
		onBlur: () => {
			if (emailSearch !== emailSearchVal) {
				emailSearch = emailSearchVal.trim();
				loadSuppressions();
			}
		},
		onClear: () => {
			emailSearchVal = '';
			emailSearch = '';
			loadSuppressions();
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
		<SuppressionList {suppressions} {loading} onDelete={handleDeleteSuppression} />
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
		padding: 0;
		flex: 1;
		overflow: auto;
	}
</style>
