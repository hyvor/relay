<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { SendRecipient } from '../../types';

	interface Props {
		recipient: SendRecipient;
		num?: number; // optionally show a number of recipients (RecipientStatuses)
	}

	let { recipient, num }: Props = $props();

	let { color, text } = $derived.by(() => {
		switch (recipient.status) {
			case 'accepted':
				return { color: 'green', text: 'Accepted' };
			case 'bounced':
				return { color: 'red', text: 'Bounced' };
			case 'complained':
				return { color: 'red', text: 'Complained' };
			case 'queued':
				return { color: 'default', text: 'Queued' };
			case 'deferred':
				return { color: 'orange', text: 'Deferred' };
			case 'failed':
				return {
					color: 'red',
					text: recipient.is_suppressed ? 'Failed (Suppressed)' : 'Failed'
				};
		}
	});
</script>

<Tag size="small" color={color as any}>
	{text}

	{#snippet end()}
		{#if num}
			<span>({num})</span>
		{/if}
	{/snippet}
</Tag>
