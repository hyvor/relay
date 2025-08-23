<script lang="ts">
	import { Loader, IconButton, Tooltip, IconMessage } from '@hyvor/design/components';
	import IconTrash from '@hyvor/icons/IconTrash';
	import type { ProjectUser, Scope } from '../../../types';
	import { getAppConfig } from '../../../lib/stores/consoleStore';

	interface Props {
		projectUsers: ProjectUser[];
		isLoadingUsers: boolean;
		availableScopes: Scope[];
		onDeleteUser: (projectUser: ProjectUser) => void;
	}

	let { projectUsers, isLoadingUsers, availableScopes, onDeleteUser }: Props = $props();

	function isCurrentUser(projectUser: ProjectUser): boolean {
		const appConfig = getAppConfig();
		return appConfig.user && projectUser.user.id === appConfig.user.id;
	}

	function formatDate(timestamp: number): string {
		return new Date(timestamp * 1000).toLocaleDateString();
	}

	function getDisplayScopes(scopes: Scope[]): { visible: Scope[]; remaining: Scope[] } {
		if (scopes.length <= 2) {
			return { visible: scopes, remaining: [] };
		}
		return {
			visible: scopes.slice(0, 2),
			remaining: scopes.slice(2)
		};
	}

</script>

{#if isLoadingUsers}
	<div class="loading-wrapper">
		<Loader>Loading project users...</Loader>
	</div>
{:else if projectUsers.length === 0}
	<IconMessage empty size="large" />
{:else}
	<div class="users-list">
		{#each projectUsers as projectUser (projectUser.id)}
			{@const displayScopes = getDisplayScopes(projectUser.scopes)}
			<div class="user-row">
				<div class="user-info">
					{#if projectUser.user.picture_url}
						<img src={projectUser.user.picture_url} alt={projectUser.user.name} class="user-avatar" />
					{:else}
						<div class="user-avatar-placeholder">
							{projectUser.user.name.charAt(0).toUpperCase()}
						</div>
					{/if}
					<div class="user-details">
						<div class="user-name">
							{projectUser.user.name}
							{#if isCurrentUser(projectUser)}
								<span class="me-tag">me</span>
							{/if}
						</div>
						<div class="user-email">{projectUser.user.email}</div>
						<div class="user-added">Added {formatDate(projectUser.created_at)}</div>
					</div>
				</div>
				<div class="user-scopes">
					{#each displayScopes.visible as scope}
						<span class="scope-tag">{scope}</span>
					{/each}
					{#if displayScopes.remaining.length > 0}
						<Tooltip text={displayScopes.remaining.join(', ')}>
							<span class="scope-tag scope-tag-more">+{displayScopes.remaining.length}</span>
						</Tooltip>
					{/if}
				</div>
				<div class="user-actions">
					{#if !isCurrentUser(projectUser)}
						<IconButton
							size="small"
							color="red"
							on:click={() => onDeleteUser(projectUser)}
							title="Remove user from project"
						>
							<IconTrash size={16} />
						</IconButton>
					{:else}
						<IconButton
							size="small"
							disabled
							title="You cannot remove yourself from the project"
						>
							<IconTrash size={16} />
						</IconButton>
					{/if}
				</div>
			</div>
		{/each}
	</div>
{/if}

<style>
	.loading-wrapper {
		text-align: center;
		padding: 40px 20px;
		color: var(--text-light);
	}

	.users-list {
		padding: 8px;
	}

	.user-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px;
		margin-bottom: 8px;
	}

	.user-row:last-child {
		margin-bottom: 0;
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
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.me-tag {
		background-color: var(--accent);
		color: white;
		padding: 2px 6px;
		border-radius: 8px;
		font-size: 11px;
		font-weight: 500;
		text-transform: lowercase;
	}

	.user-email {
		font-size: 13px;
		color: var(--text-light);
	}

	.user-added {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 2px;
	}

	.user-scopes {
		display: flex;
		gap: 6px;
		flex-wrap: wrap;
		margin: 0 16px;
	}

	.scope-tag {
		background-color: var(--accent-lightest);
		color: var(--accent-dark);
		padding: 2px 8px;
		border-radius: 12px;
		font-size: 11px;
		font-weight: 500;
	}

	.user-actions {
		display: flex;
		gap: 8px;
	}
</style>
