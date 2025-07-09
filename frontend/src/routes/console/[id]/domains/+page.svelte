<script lang="ts">
	import {
		Button,
		TextInput,
		toast,
		IconButton,
		Modal,
		SplitControl,
		Loader
	} from '@hyvor/design/components';
	import IconPlus from '@hyvor/icons/IconPlus';
	import IconX from '@hyvor/icons/IconX';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import DomainModal from './DomainModal.svelte';
	import DomainList from './DomainList.svelte';
	import DnsRecordModal from './DnsRecordModal.svelte';
	import type { Domain } from '../../types';
	import { getDomains, deleteDomain, verifyDomain } from '../../lib/actions/domainActions';
	import { onMount } from 'svelte';

	let domains: Domain[] = $state([]);
	let loading = $state(true);
	let loadingMore = $state(false);
	let showCreateModal = $state(false);
	let showDnsModal = $state(false);
	let showDeleteModal = $state(false);
	let domainToDelete = $state<Domain | null>(null);
	let deleteConfirmationText = $state('');
	let selectedDomain = $state<Domain | null>(null);
	let domainSearchVal = $state('');
	let domainSearch = $state('');
	let hasMore = $state(true);

	const LIMIT = 50;
	let offset = $state(0);

	onMount(() => {
		loadDomains();
	});

	function loadDomains(reset = false) {
		if (reset) {
			offset = 0;
			domains = [];
			hasMore = true;
		}

		const currentOffset = reset ? 0 : offset;
		const isInitialLoad = currentOffset === 0;
		
		if (isInitialLoad) {
			loading = true;
		} else {
			loadingMore = true;
		}

		const search = domainSearch === '' ? undefined : domainSearch;

		getDomains(search, LIMIT, currentOffset)
			.then((newDomains) => {
				if (reset) {
					domains = newDomains;
				} else {
					domains = [...domains, ...newDomains];
				}
				
				hasMore = newDomains.length === LIMIT;
				offset = currentOffset + newDomains.length;
			})
			.catch((error) => {
				console.error('Failed to load domains:', error);
				toast.error('Failed to load domains');
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	const searchActions = {
		onKeydown: (e: KeyboardEvent) => {
			if (e.key === 'Enter') {
				domainSearch = domainSearchVal.trim();
				loadDomains(true);
			}
		},
		onBlur: () => {
			if (domainSearch !== domainSearchVal) {
				domainSearch = domainSearchVal.trim();
				loadDomains(true);
			}
		},
		onClear: () => {
			domainSearchVal = '';
			domainSearch = '';
			loadDomains(true);
		}
	};

	function handleLoadMore() {
		if (!loadingMore && hasMore) {
			loadDomains();
		}
	}

	function handleDomainCreated(domain: Domain) {
		loadDomains(true);
		showCreateModal = false;
		selectedDomain = domain;
		showDnsModal = true;
	}

	function handleDeleteDomain(domain: Domain) {
		domainToDelete = domain;
		deleteConfirmationText = '';
		showDeleteModal = true;
	}

	function handleConfirmDelete() {
		if (!domainToDelete) return;
		
		if (deleteConfirmationText.trim() !== domainToDelete.domain) {
			toast.error('Domain name does not match');
			return;
		}

		const domainId = domainToDelete.id;
		showDeleteModal = false;
		domainToDelete = null;
		deleteConfirmationText = '';

		deleteDomain(domainId)
			.then(() => {
				loadDomains(true);
				toast.success('Domain deleted');
			})
			.catch((error) => {
				console.error('Failed to delete domain:', error);
				toast.error('Failed to delete domain');
			});
	}

	function handleCancelDelete() {
		showDeleteModal = false;
		domainToDelete = null;
		deleteConfirmationText = '';
	}

	function handleVerifyDomain(domain: Domain) {
		verifyDomain(domain.id, domain.domain)
			.then(() => {
				loadDomains(true);
				toast.success('Domain verification initiated');
			})
			.catch((error) => {
				console.error('Failed to verify domain:', error);
				toast.error('Failed to verify domain');
			});
	}


</script>

<SingleBox>
	<div class="top">
		<div class="search-section">
			<div class="search-wrap">
				<TextInput
					bind:value={domainSearchVal}
					placeholder="Search domains..."
					on:keydown={searchActions.onKeydown}
					on:blur={searchActions.onBlur}
					size="small"
				>
					{#snippet end()}
						{#if domainSearchVal.trim() !== ''}
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

				{#if domainSearch !== domainSearchVal}
					<span class="press-enter"> ⏎ </span>
				{/if}
			</div>
		</div>
		
		<Button variant="fill" on:click={() => (showCreateModal = true)}>
			<IconPlus size={16} />
			Create Domain
		</Button>
	</div>

	<div class="content">
		{#if loading}
			<div class="loader-container">
				<Loader />
			</div>
		{:else}
			<DomainList
				{domains}
				loading={false}
				onDelete={handleDeleteDomain}
				onVerify={handleVerifyDomain}
			/>

			{#if hasMore && !loading && domains.length > 0}
				<div class="load-more">
					<Button
						variant="outline"
						on:click={handleLoadMore}
						disabled={loadingMore}
					>
						{loadingMore ? 'Loading...' : 'Load More'}
					</Button>
				</div>
			{/if}
		{/if}
	</div>
</SingleBox>

<DomainModal 
	bind:show={showCreateModal} 
	onDomainCreated={handleDomainCreated}
/>

{#if selectedDomain}
	<DnsRecordModal 
		domain={selectedDomain} 
		bind:show={showDnsModal} 
	/>
{/if}

{#if domainToDelete}
	<Modal
		bind:show={showDeleteModal}
		title="Delete Domain"
		size="medium"
		footer={{
			cancel: {
				text: 'Cancel'
			},
			confirm: {
				text: 'Delete Domain'
			}
		}}
		on:cancel={handleCancelDelete}
		on:confirm={handleConfirmDelete}
	>
		<div>
			<div class="confirm-text">
				You are about to delete the domain <strong>{domainToDelete.domain}</strong>. This action cannot be undone.
				Type the domain name to confirm:
			</div>
			<TextInput
				bind:value={deleteConfirmationText}
				placeholder={domainToDelete.domain}
				block
				autofocus
			/>
		
		</div>
	</Modal>
{/if}

<style>
	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
		gap: 20px;
	}

	.search-section {
		display: flex;
		align-items: center;
		gap: 12px;
		flex: 1;
	}

	.search-wrap {
		display: flex;
		gap: 10px;
		align-items: center;
		max-width: 300px;

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
	}
	
	.loader-container {
		display: flex;
		justify-content: center;
		align-items: center;
		flex: 1;
	}

	.load-more {
		display: flex;
		justify-content: center;
		margin-top: 20px;
	}

	.confirm-text {
		margin-bottom: 10px;
	}
</style>
