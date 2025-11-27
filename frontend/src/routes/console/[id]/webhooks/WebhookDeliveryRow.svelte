<script lang="ts">
	import dayjs from 'dayjs';
	import type { WebhookDelivery } from '../../types';
	import { Tag } from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconCaretUp from '@hyvor/icons/IconCaretUp';

	interface Props {
		delivery: WebhookDelivery;
	}

	let { delivery }: Props = $props();
	let opened = $state(false);

	function getStatusColor(status: string, isRetrying: boolean) {
		if (isRetrying) {
			return 'orange';
		}
		switch (status) {
			case 'delivered':
				return 'green';
			case 'failed':
				return 'red';
			case 'pending':
				return 'orange';
			default:
				return 'default';
		}
	}

	function getStatusText(delivery: WebhookDelivery): string {
		if (delivery.try_count > 0 && delivery.status === 'pending') {
			return `retrying (${delivery.try_count})`;
		}
		return delivery.status;
	}

	function isRetrying(delivery: WebhookDelivery): boolean {
		return delivery.try_count > 0 && delivery.status === 'pending';
	}

	function truncateUrl(url: string, maxLength: number = 60) {
		if (url.length <= maxLength) return url;
		return url.substring(0, maxLength) + '...';
	}
</script>

<div class="wrap">
	<button class="row" onclick={() => (opened = !opened)} class:opened>
		<div class="status">
			<Tag color={getStatusColor(delivery.status, isRetrying(delivery))} size="small">
				{getStatusText(delivery)}
			</Tag>
		</div>
		<div class="date">
			{dayjs.unix(delivery.created_at).format('YYYY-MM-DD HH:mm:ss')}
		</div>
		<div class="event">
			<Tag variant="gray" size="small">{delivery.event}</Tag>
		</div>
		<div class="code">
			<div class="code-number">
				{#if delivery.response_code}
					HTTP {delivery.response_code}
				{:else}
					<span class="no-code">No response</span>
				{/if}
			</div>
		</div>
		<div class="url">
			{truncateUrl(delivery.url)}
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
				<div class="detail-label">ID:</div>
				<div class="detail-value">{delivery.id}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">URL:</div>
				<div class="detail-value">{delivery.url}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Event:</div>
				<div class="detail-value">{delivery.event}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Status:</div>
				<div class="detail-value">{getStatusText(delivery)}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Response Code:</div>
				<div class="detail-value">{delivery.response_code ?? 'N/A'}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Try Count:</div>
				<div class="detail-value">{delivery.try_count}</div>
			</div>
			<div class="detail-row">
				<div class="detail-label">Created:</div>
				<div class="detail-value">{dayjs.unix(delivery.created_at).format('YYYY-MM-DD HH:mm:ss')}</div>
			</div>
			{#if delivery.response}
				<div class="detail-row response-row">
					<div class="detail-label">Response:</div>
					<div class="detail-value">
						<pre class="response-body">{delivery.response}</pre>
					</div>
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
		grid-template-columns: 140px 180px 150px 120px 1fr 50px;
		gap: 10px;
		padding: 15px 25px;
		border-radius: 20px;
		cursor: pointer;
		text-align: left;
        font-size: 14px;
	}
	.row.opened {
		background-color: var(--hover);
	}
	.row:hover {
		background-color: var(--hover);
	}
    
	.url {
		color: var(--text-light);
		font-size: 14px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.date {
		font-size: 14px;
		color: var(--text-light);
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

	.detail-row.response-row {
		flex-direction: column;
		margin-top: 15px;
		padding-top: 15px;
		border-top: 1px solid var(--border);
	}

	.detail-label {
		font-weight: 600;
		width: 150px;
		flex-shrink: 0;
	}

	.response-row .detail-label {
		width: 100%;
		margin-bottom: 10px;
	}

	.detail-value {
		color: var(--text-light);
		word-break: break-word;
	}

	.response-body {
		background-color: var(--input-bg);
		padding: 12px;
		border-radius: 4px;
		overflow-x: auto;
		font-size: 13px;
		line-height: 1.5;
		white-space: pre-wrap;
		word-break: break-word;
		margin: 0;
		width: 100%;
	}
</style>

