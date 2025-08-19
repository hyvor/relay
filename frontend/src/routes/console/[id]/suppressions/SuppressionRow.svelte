<script lang="ts">
	import { IconButton, Tag } from '@hyvor/design/components';
	import IconTrash from '@hyvor/icons/IconTrash';
	import type { Suppression } from '../../types';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import { cant } from '../../lib/scope.svelte';

	interface Props {
		suppression: Suppression;
		onDelete: (suppression: Suppression) => void;
	}

	let { suppression, onDelete }: Props = $props();

	function getReasonColor(reason: string): 'red' | 'orange' | 'default' {
		switch (reason) {
			case 'bounce':
				return 'red';
			case 'complaint':
				return 'orange';
			default:
				return 'default';
		}
	}

	function getReasonLabel(reason: string): string {
		switch (reason) {
			case 'bounce':
				return 'Bounce';
			case 'complaint':
				return 'Complaint';
			default:
				return reason;
		}
	}
</script>

<div class="suppression-row">
	<div class="suppression-info">
		<div class="suppression-header">
			<div class="email">{suppression.email}</div>
			<div class="reason">
				<Tag color={getReasonColor(suppression.reason)} size="small">
					{getReasonLabel(suppression.reason)}
				</Tag>
			</div>
		</div>
		{#if suppression.description}
			<p class="suppression-description">{suppression.description}</p>
		{/if}
		<div class="suppression-meta">
			<span class="created-label">Added:</span>
			<RelativeTime unix={suppression.created_at} />
		</div>
	</div>

	<div class="suppression-actions">
		<IconButton
			variant="fill-light"
			color="red"
			size="small"
			on:click={() => onDelete(suppression)}
			disabled={cant('suppressions.write')}
		>
			<IconTrash size={12} />
		</IconButton>
	</div>
</div>

<style>
	.suppression-row {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		padding: 20px;
		gap: 20px;
		background: white;
	}

	.suppression-info {
		flex: 1;
		min-width: 0;
	}

	.suppression-header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 8px;
	}

	.email {
		font-size: 16px;
		font-weight: 600;
		color: var(--text);
		word-break: break-all;
	}

	.reason {
		flex-shrink: 0;
	}

	.suppression-description {
		margin: 0 0 8px 0;
		font-size: 14px;
		color: var(--text-light);
		line-height: 1.4;
	}

	.suppression-meta {
		display: flex;
		align-items: center;
		gap: 4px;
		font-size: 12px;
		color: var(--text-light);
	}

	.created-label {
		font-weight: 500;
	}

	.suppression-actions {
		display: flex;
		gap: 8px;
		flex-shrink: 0;
	}

	@media (max-width: 640px) {
		.suppression-row {
			flex-direction: column;
			align-items: stretch;
		}

		.suppression-actions {
			justify-content: flex-end;
		}
	}
</style>
