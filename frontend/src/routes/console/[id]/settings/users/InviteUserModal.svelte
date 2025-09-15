<script lang="ts">
	import { Modal, Checkbox, SplitControl } from '@hyvor/design/components';
	import type { ProjectUserMiniObject, Scope } from '../../../types';

	interface Props {
		show: boolean;
		selectedUser: ProjectUserMiniObject | null;
		selectedScopes: string[];
		isInviting: boolean;
		isEditing: boolean;
		availableScopes: Scope[];
		onCancel: () => void;
		onConfirm: () => void;
	}

	let {
		show = $bindable(),
		selectedUser,
		selectedScopes = $bindable(),
		isInviting,
		isEditing,
		availableScopes,
		onCancel,
		onConfirm
	}: Props = $props();

	// Filter out 'sends.send' when not editing
	const filteredScopes = $derived(() => {
		if (isEditing) {
			return availableScopes;
		}
		return availableScopes.filter(scope => scope !== 'sends.send');
	});

	function handleSelectAll() {
		console.log('handleSelectAll called', { filteredScopes: filteredScopes(), selectedScopes });
		selectedScopes = [...filteredScopes()];
		console.log('after select all', { selectedScopes });
	}

	function handleDeselectAll() {
		console.log('handleDeselectAll called', { selectedScopes });
		selectedScopes = [];
		console.log('after deselect all', { selectedScopes });
	}
</script>

<Modal
	bind:show
	loading={isInviting}
	size="medium"
	title="Invite User to Project"
	footer={{
		cancel: { text: 'Cancel' },
		confirm: { text: 'Invite' }
	}}
	on:cancel={onCancel}
	on:confirm={onConfirm}
>
	<div class="modal-content">
		{#if selectedUser}
			<div class="invited-user">
				{#if selectedUser.picture_url}
					<img src={selectedUser.picture_url} alt={selectedUser.name} class="user-avatar" />
				{:else}
					<div class="user-avatar-placeholder">
						{selectedUser.name.charAt(0).toUpperCase()}
					</div>
				{/if}
				<div class="user-details">
					<div class="user-name">{selectedUser.name}</div>
					<div class="user-email">{selectedUser.email}</div>
				</div>
			</div>

			<SplitControl
				label="Permissions"
				caption="Select the permissions this user will have in the project"
			>
				<div class="scopes-header">
					<div class="scopes-actions">
						<button
							type="button"
							class="scope-action-btn"
							disabled={isInviting || selectedScopes.length === filteredScopes().length}
							onclick={handleSelectAll}
						>
							Select all
						</button>
						<button
							type="button"
							class="scope-action-btn"
							disabled={isInviting || selectedScopes.length === 0}
							onclick={handleDeselectAll}
						>
							Deselect all
						</button>
					</div>
				</div>
				<div class="scopes-container">
					{#each filteredScopes() as scope (scope)}
						<div class="scope-item">
							<Checkbox
								checked={selectedScopes.includes(scope)}
								bind:group={selectedScopes}
								value={scope}
							>
								<div class="scope-content">
									<span class="scope-name">{scope}</span>
								</div>
							</Checkbox>
						</div>
					{/each}
				</div>
			</SplitControl>
		{/if}
	</div>
</Modal>

<style>
	.modal-content {
		max-height: 70vh;
		overflow-y: auto;
	}

	.invited-user {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 16px;
		background-color: var(--background-light);
		border-radius: var(--box-radius);
	}

	.user-avatar {
		width: 40px;
		height: 40px;
		object-fit: cover;
		border-radius: 50%;
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

	.scopes-header {
		margin-bottom: 12px;
	}

	.scopes-actions {
		display: flex;
		gap: 16px;
	}

	.scope-action-btn {
		background: none;
		border: none;
		color: var(--primary);
		cursor: pointer;
		font-size: 14px;
		padding: 0;
		text-decoration: underline;
		transition: color 0.2s;
	}

	.scope-action-btn:hover:not(:disabled) {
		color: var(--primary-dark);
	}

	.scope-action-btn:disabled {
		color: var(--text-light);
		cursor: not-allowed;
		text-decoration: none;
	}

	.scopes-container {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.scope-item {
		display: flex;
		align-items: center;
	}

	.scope-content {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 8px;
	}

	.scope-name {
		font-weight: 500;
	}
</style>
