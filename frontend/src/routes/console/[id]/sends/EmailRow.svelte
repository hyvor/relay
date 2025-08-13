<script lang="ts">
	import type { Email } from '../../types';
	import SendStatus from './SendStatus.svelte';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import { consoleUrlProject } from '../../lib/consoleUrl';

	interface Props {
		email: Email;
		refreshList: () => void;
	}

	let { email, refreshList }: Props = $props();

	const statusTimestamp = $derived(
		email.status === 'accepted'
			? email.accepted_at
			: email.status === 'bounced'
				? email.bounced_at
				: null
	);
</script>

<a class="email" href={consoleUrlProject(`sends/${email.uuid}`)}>
	<div class="email-wrap">
		<div class="email-details">
			<div class="email-row">
				<span class="email-label">From:</span>
				<span class="email-address">{email.from_address}</span>
			</div>
			<div class="email-row">
				<span class="email-label">To:</span>
				<span class="email-address">{email.to_address}</span>
			</div>
		</div>
	</div>

	<div class="subject-wrap">
		<div class="subject-label">Subject:</div>
		<div class="subject">{email.subject}</div>
	</div>

	<div class="status-wrap">
		<div class="status">
			<SendStatus status={email.status} />
			<div class="timestamps">
				<div class="timestamp">
					<span class="timestamp-label">Created:</span>
					<RelativeTime unix={email.created_at} />
				</div>
				{#if statusTimestamp}
					<div class="timestamp">
						<span class="timestamp-label">
							{email.status === 'accepted' ? 'Accepted:' : 'Failed:'}
						</span>
						<RelativeTime unix={statusTimestamp} />
					</div>
				{/if}
			</div>
		</div>
	</div>
</a>

<style>
	.email {
		padding: 15px 25px;
		border-radius: var(--box-radius);
		display: grid;
		grid-template-columns: minmax(250px, 1fr) minmax(300px, 2fr) auto;
		text-align: left;
		width: 100%;
		align-items: center;
		gap: 20px;
	}
	.email:hover {
		background: var(--hover);
	}

	.email-wrap {
		min-width: 250px;
	}

	.subject-wrap {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 8px;
	}

	.subject-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: 500;
	}

	.subject {
		font-weight: 600;
		color: var(--text);
		font-size: 14px;
	}

	.email-details {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.email-row {
		display: flex;
		align-items: center;
		gap: 6px;
	}

	.email-label {
		font-size: 12px;
		color: var(--text-light);
		font-weight: 500;
	}

	.email-address {
		font-weight: 600;
		color: var(--text);
		font-size: 14px;
	}

	.status-wrap {
		display: flex;
		align-items: center;
		justify-content: flex-end;
	}

	.status {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 4px;
	}

	.timestamps {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 2px;
	}

	.timestamp {
		display: flex;
		align-items: center;
		gap: 4px;
		font-size: 12px;
		color: var(--text-light);
	}

	.timestamp-label {
		font-weight: 500;
	}

	@media (max-width: 992px) {
		.email {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			gap: 10px;
		}

		.subject-wrap {
			width: 100%;
		}

		.status-wrap {
			width: 100%;
			justify-content: flex-start;
		}

		.status {
			align-items: flex-start;
		}

		.timestamps {
			align-items: flex-start;
		}
	}
</style>
