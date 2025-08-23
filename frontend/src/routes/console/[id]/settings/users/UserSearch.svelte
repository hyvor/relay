<script lang="ts">
	import { TextInput, SplitControl, Loader, IconMessage } from '@hyvor/design/components';
	import IconSearch from '@hyvor/icons/IconSearch';
	import UserCard from './UserCard.svelte';
	import type { ProjectUserSearchResult, ProjectUser } from '../../../types';

	interface Props {
		searchEmail: string;
		searchResults: ProjectUserSearchResult[];
		isSearching: boolean;
		hasSearched: boolean;
		projectUsers: ProjectUser[];
		onSearchEnter: () => void;
		onInviteUser: (user: ProjectUserSearchResult) => void;
	}

	let {
		searchEmail = $bindable(),
		searchResults,
		isSearching,
		hasSearched,
		projectUsers,
		onSearchEnter,
		onInviteUser
	}: Props = $props();

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'Enter') {
			onSearchEnter();
		}
	}
</script>

<SplitControl label="Search by Email" caption="Enter an email address.">
	<div class="search-container">
		<div class="search-input-wrapper">
			<TextInput
				block
				bind:value={searchEmail}
				on:keydown={handleKeydown}
				placeholder="user@example.com"
				disabled={isSearching}
			/>
			{#if isSearching}
				<div class="search-indicator">
					<Loader size="small" />
				</div>
			{:else}
				<IconSearch class="search-icon" />
			{/if}
		</div>

		{#if searchResults.length > 0}
			<div class="search-results">
				{#each searchResults as user (user.id)}
					<UserCard
						{user}
						isAlreadyAdded={projectUsers.some(pu => pu.user.email === user.email)}
						onInvite={() => onInviteUser(user)}
					/>
				{/each}
			</div>
		{:else if hasSearched && searchResults.length === 0 && !isSearching && searchEmail.trim()}
			<div class="no-results">
				<IconMessage empty>No users found with email "{searchEmail}"</IconMessage>
			</div>
		{/if}
	</div>
</SplitControl>

<style>
	.search-container {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	.search-input-wrapper {
		position: relative;
		display: flex;
		align-items: center;
	}

	.search-input-wrapper :global(.search-icon) {
		position: absolute;
		right: 12px;
		color: var(--text-light);
		pointer-events: none;
	}

	.search-indicator {
		position: absolute;
		right: 12px;
	}

	.search-results {
		display: flex;
		flex-direction: column;
		gap: 8px;
		padding: 16px;
		background: var(--background-light);
	}

	.no-results {
		text-align: center;
		padding: 32px 16px;
	}
</style>
