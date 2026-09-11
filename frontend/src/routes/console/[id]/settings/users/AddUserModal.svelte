<script lang="ts">
	import { Modal, Checkbox, SplitControl } from '@hyvor/design/components';
	import type { Scope } from '../../../types';

	interface Props {
		show: boolean;
		selectedScopes: string[];
		isAdding: boolean;
		isEditing: boolean;
		availableScopes: Scope[];
		onCancel: () => void;
		onConfirm: () => void;
	}

	let {
		show = $bindable(),
		selectedScopes = $bindable(),
		isAdding,
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
		selectedScopes = [...filteredScopes()];
	}

	function handleDeselectAll() {
		selectedScopes = [];
	}
</script>

<Modal
	bind:show
	loading={isAdding}
	size="medium"
	title="Add User to Project"
	footer={{
		cancel: { text: 'Cancel' },
		confirm: { text: 'Add user' }
	}}
	on:cancel={onCancel}
	on:confirm={onConfirm}
>
	<div class="modal-content">
		<SplitControl
			label="Permissions"
			caption="Select the permissions this user will have in the project"
		>
			<div class="scopes-header">
				<div class="scopes-actions">
					<button
						type="button"
						class="scope-action-btn"
						disabled={isAdding || selectedScopes.length === filteredScopes().length}
						onclick={handleSelectAll}
					>
						Select all
					</button>
					<button
						type="button"
						class="scope-action-btn"
						disabled={isAdding || selectedScopes.length === 0}
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
	</div>
</Modal>

<style>
	.modal-content {
		max-height: 70vh;
		overflow-y: auto;
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
