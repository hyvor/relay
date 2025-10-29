<script lang="ts">
	import dayjs from 'dayjs';
	import type { InfrastructureBounce } from '../../sudoTypes';
	import { Button, Tag, toast } from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconCaretUp from '@hyvor/icons/IconCaretUp';
	import { markInfrastructureBounceAsRead } from '../../sudoActions';

	interface Props {
		bounce: InfrastructureBounce;
		onMarkAsRead?: (id: number) => void;
	}

	let { bounce, onMarkAsRead }: Props = $props();
	let opened = $state(false);
	let marking = $state(false);

	async function handleMarkAsRead() {
		if (marking) return;
		marking = true;
		try {
			await markInfrastructureBounceAsRead(bounce.id);
			toast.success('Marked as read');
			if (onMarkAsRead) {
				onMarkAsRead(bounce.id);
			}
		} catch (error: any) {
			toast.error('Failed to mark as read: ' + error.message);
		} finally {
			marking = false;
		}
	}
</script>

<div class="wrap">
	<button class="row" onclick={() => (opened = !opened)} class:opened>
		<div class="status">
			{#if bounce.is_read}
				<Tag color="default">Read</Tag>
			{:else}
				<Tag color="blue">Unread</Tag>
			{/if}
		</div>
		<div class="date">
			{dayjs.unix(bounce.created_at).format('YYYY-MM-DD HH:mm:ss')}
		</div>
		<div class="code">
			<div class="code-number">SMTP {bounce.smtp_code}</div>
			<div class="enhanced-code">{bounce.smtp_enhanced_code}</div>
		</div>
		<div class="message">
			{bounce.smtp_message}
		</div>
		<div class="caret">
			{#if opened}
				<IconCaretUp />
			{:else}
				<IconCaretDown />
			{/if}
		</div>
	</button>

	{#if opened}
		<div class="details">
			<div class="detail-row">
				<div class="detail-label">Send Recipient ID:</div>
				<div class="detail-value">{bounce.send_recipient_id}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">SMTP Code:</div>
				<div class="detail-value">{bounce.smtp_code}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Enhanced Code:</div>
				<div class="detail-value">{bounce.smtp_enhanced_code}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Message:</div>
				<div class="detail-value">{bounce.smtp_message}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Created:</div>
				<div class="detail-value">{dayjs.unix(bounce.created_at).format('YYYY-MM-DD HH:mm:ss')}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Updated:</div>
				<div class="detail-value">{dayjs.unix(bounce.updated_at).format('YYYY-MM-DD HH:mm:ss')}</div>
			</div>

			{#if !bounce.is_read}
				<div class="actions">
					<Button size="small" onclick={handleMarkAsRead} disabled={marking}>
						{marking ? 'Marking...' : 'Mark as Read'}
					</Button>
				</div>
			{/if}
		</div>
	{/if}
</div>

<style>
	.row {
		width: 100%;
		display: grid;
		align-items: center;
		grid-template-columns: 100px 180px 200px 1fr 50px;
		gap: 10px;
		padding: 15px 25px;
		border-radius: 20px;
		cursor: pointer;
		text-align: left;
	}
	.row.opened {
		background-color: var(--hover);
	}
	.row:hover {
		background-color: var(--hover);
	}

	.code-number {
		font-weight: 600;
	}

	.enhanced-code {
		color: var(--text-light);
		font-size: 14px;
		margin-top: 2px;
	}

	.message {
		color: var(--text-light);
		font-size: 14px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.caret {
		text-align: right;
	}

	.details {
		margin-left: 25px;
		padding: 15px;
		border-left: 2px solid var(--border);
	}

	.detail-row {
		display: flex;
		margin-bottom: 10px;
		font-size: 14px;
	}

	.detail-label {
		font-weight: 600;
		width: 180px;
		flex-shrink: 0;
	}

	.detail-value {
		color: var(--text-light);
		word-break: break-word;
	}

	.actions {
		margin-top: 15px;
		padding-top: 15px;
		border-top: 1px solid var(--border);
	}
</style>

