<script lang="ts">
	import { Button, Callout } from '@hyvor/design/components';
	import IconArrowClockwise from '@hyvor/icons/IconArrowClockwise';

	interface Props {
		onRetryClick: () => void;
	}

	let { onRetryClick }: Props = $props();
</script>

<div class="wrap">
	<Callout type="warning">
		{#snippet icon()}
			<IconArrowClockwise />
		{/snippet}
		Some recipients failed to receive this email. You can retry sending to the failed recipients.
		<div class="retry-actions">
			<Button size="small" color="orange" on:click={onRetryClick}>
				Retry
			</Button>
		</div>
	</Callout>
</div>

<style>
	.wrap {
		margin-bottom: 20px;
	}
	.retry-actions {
		display: flex;
		gap: 8px;
		margin-top: 10px;
	}
</style>
