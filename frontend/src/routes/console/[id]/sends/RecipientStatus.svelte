<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { SendRecipient } from '../../types';

	interface Props {
		recipient: SendRecipient;
	}

	let { recipient }: Props = $props();

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
			case 'suppressed':
				return { color: 'red', text: 'Suppressed' };
			case 'failed':
				return { color: 'red', text: 'Failed' };
		}
	});
</script>

<Tag size="small" color={color as any}>
	{text}
</Tag>
