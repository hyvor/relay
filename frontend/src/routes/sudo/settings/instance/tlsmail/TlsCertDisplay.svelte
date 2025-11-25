<script lang="ts">
	import dayjs from 'dayjs';
	import type { TlsCertificate } from '../../../sudoTypes';
	import IconPatchCheck from '@hyvor/icons/IconPatchCheck';
	import { Tag, Tooltip } from '@hyvor/design/components';

	interface Props {
		cert: TlsCertificate;
		type: 'current' | 'latest';
	}

	let { cert, type }: Props = $props();
</script>

<div class="certificate">
	<IconPatchCheck size={32} />
	<div class="details">
		<div class="domain">
			{cert.domain}

			{#if type === 'current'}
				<Tooltip text="The mail server is currently using this certificate.">
					<Tag size="small">Current Certificate</Tag>
				</Tooltip>
			{:else if type === 'latest'}
				<Tooltip
					text="This certificate is not currently in use. If successfully generated, the mail server will start using this certificate."
				>
					<Tag size="small">Not in Use</Tag>
				</Tooltip>
			{/if}
		</div>
		<div class="status">
			{#if cert.status === 'pending'}
				<Tag size="small">Generating</Tag>
			{:else if cert.status === 'failed'}
				<Tag size="small" color="red">Failed</Tag>
			{:else if cert.status === 'active'}
				<Tag size="small" color="green">Active</Tag>
			{:else if cert.status === 'expired'}
				<Tag size="small" color="orange">Expired</Tag>
			{:else if cert.status === 'revoked'}
				<Tag size="small">Revoked</Tag>
			{/if}
		</div>
	</div>
	<div class="dates">
		{#if cert.valid_from && cert.valid_to}
			<div class="from">
				Valid from: {dayjs.unix(cert.valid_from).format('YYYY-MM-DD')}
			</div>
			<div class="to">
				Valid to: {dayjs.unix(cert.valid_to).format('YYYY-MM-DD')}
			</div>
		{/if}
	</div>
</div>

<style>
	.certificate {
		background-color: var(--input);
		padding: 15px 20px;
		margin-bottom: 15px;
		border-radius: 20px;
		display: flex;
		gap: 15px;
		align-items: center;
	}
	.details {
		flex: 1;
	}
	.domain {
		font-weight: 600;
	}
	.status {
		margin-top: 3px;
	}

	.dates {
		text-align: right;
		font-size: 14px;
		color: var(--text-light);
	}
</style>
