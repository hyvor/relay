<script lang="ts">
	import { Modal, Checkbox, SplitControl } from '@hyvor/design/components';
	import type { ProjectUserSearchResult, Scope } from '../../../types';

	interface Props {
		show: boolean;
		selectedUser: ProjectUserSearchResult | null;
		selectedScopes: string[];
		isInviting: boolean;
		availableScopes: Scope[];
		onCancel: () => void;
		onConfirm: () => void;
	}

	let {
		show = $bindable(),
		selectedUser,
		selectedScopes = $bindable(),
		isInviting,
		availableScopes,
		onCancel,
		onConfirm
	}: Props = $props();
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
	{#if selectedUser}
		<div class="modal-content">
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
				column
			>
				<div class="scopes-list">
					{#each availableScopes as scope}
						<label class="scope-item">
							<Checkbox
								bind:group={selectedScopes}
								value={scope}
							>
								{scope}
							</Checkbox>
						</label>
					{/each}
				</div>
			</SplitControl>
		</div>
	{/if}
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}

	.invited-user {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 16px;
		background-color: var(--background-light);
		margin-bottom: 24px;
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

	.scopes-list {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	.scope-item {
		display: flex;
		align-items: flex-start;
		gap: 12px;
		cursor: pointer;
		padding: 8px;
		border-radius: var(--box-radius);
		transition: background-color 0.2s;
	}

	.scope-item:hover {
		background-color: var(--hover);
	}
</style>
