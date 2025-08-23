<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { SendRecipientStatus } from '../../types';

	interface Props {
		status: SendRecipientStatus;
		num?: number; // optionally show a number of recipients (RecipientStatuses)
	}

	let { status, num }: Props = $props();

	let { color, text } = $derived.by(() => {
		switch (status) {
			case 'accepted':
				return { color: 'green', text: 'Accepted' };
			case 'bounced':
				return { color: 'red', text: 'Bounced' };
			case 'complained':
				return { color: 'red', text: 'Complained' };
			case 'queued':
				return { color: 'default', text: 'Queued' };
			case 'retrying':
				return { color: 'orange', text: 'Retrying' };
			case 'failed':
				return { color: 'red', text: 'Failed' };
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
