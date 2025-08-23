<script lang="ts">
	import { Button } from '@hyvor/design/components';
	import IconPlus from '@hyvor/icons/IconPlus';
	import type { ProjectUserSearchResult } from '../../../types';

	interface Props {
		user: ProjectUserSearchResult;
		isAlreadyAdded: boolean;
		onInvite: () => void;
	}

	let { user, isAlreadyAdded, onInvite }: Props = $props();
</script>

<div class="user-card">
	<div class="user-info">
		{#if user.picture_url}
			<img src={user.picture_url} alt={user.name} class="user-avatar" />
		{:else}
			<div class="user-avatar-placeholder">
				{user.name.charAt(0).toUpperCase()}
			</div>
		{/if}
		<div class="user-details">
			<div class="user-name">{user.name}</div>
			<div class="user-email">{user.email}</div>
		</div>
	</div>
	<Button
		size="small"
		on:click={onInvite}
		disabled={isAlreadyAdded}
	>
		{#snippet start()}
			<IconPlus size={14} />
		{/snippet}
		{isAlreadyAdded ? 'Already Added' : 'Invite'}
	</Button>
</div>

<style>
	.user-card {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px;
		background: var(--background);
	}

	.user-info {
		display: flex;
		align-items: center;
		gap: 12px;
		flex: 1;
	}

	.user-avatar {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		object-fit: cover;
	}

	.user-avatar-placeholder {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		background-color: var(--accent);
		display: flex;
		align-items: center;
		justify-content: center;
		font-weight: 600;
		color: white;
	}

	.user-details {
		flex: 1;
	}

	.user-name {
		font-weight: 600;
		margin-bottom: 2px;
	}

	.user-email {
		font-size: 13px;
		color: var(--text-light);
	}
</style>
