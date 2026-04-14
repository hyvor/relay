<script lang="ts">
	import { Button, Callout } from '@hyvor/design/components';
	import IconHourglassSplit from '@hyvor/icons/IconHourglassSplit';
	import type { SendRecipient } from '../../../types';

	interface Props {
		after: number;
		recipients: SendRecipient[];
		onTryNow?: () => void;
		tryNowLoading?: boolean;
	}

	let { after, recipients, onTryNow, tryNowLoading = false }: Props = $props();
	const hasDeferredRecipients = $derived(recipients.some((r) => r.status === 'deferred'));

	function getIn(): string {
		const now = Math.floor(Date.now() / 1000);
		const diff = after - now;

		if (diff <= 0) {
			return 'shortly';
		}

		const minutes = Math.floor(diff / 60);
		const hours = Math.floor(diff / 3600);

		if (hours > 0) {
			return hours === 1 ? 'in 1 hour' : `in ${hours} hours`;
		} else if (minutes > 0) {
			return minutes === 1 ? 'in 1 minute' : `in ${minutes} minutes`;
		} else {
			return 'in a few seconds';
		}
	}
</script>

<div class="wrap">
	<Callout type="info">
		{#snippet icon()}
			<IconHourglassSplit />
		{/snippet}
		This send will be {hasDeferredRecipients ? 'retried' : 'sent'}
		{getIn()}.
		{#if onTryNow}
			<div class="try-now-actions">
				<Button size="small" on:click={onTryNow} loading={tryNowLoading}>
					Try Now
				</Button>
			</div>
		{/if}
	</Callout>
</div>

<style>
	.wrap {
		margin-bottom: 20px;
	}
	.try-now-actions {
		margin-top: 10px;
	}
</style>
