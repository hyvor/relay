<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { EmailStatus } from '../../types';

	interface Props {
		status: EmailStatus;
	}

	let { status }: Props = $props();

	let color: 'green' | 'red' | 'default' = $derived(
		status === 'sent' ? 'green' : status === 'failed' ? 'red' : 'default'
	);
</script>

<Tag {color}>
	{status.charAt(0).toUpperCase() + status.slice(1)}
</Tag>
