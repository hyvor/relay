<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { EmailStatus } from '../../types';

	export let status: EmailStatus;

	let color: 'green' | 'orange' | 'default';

	$: {
		color = status === 'sent' ? 'green' : status === 'failed' ? 'orange' : 'default';
	}
</script>

<Tag {color}>
	{status.charAt(0).toUpperCase() + status.slice(1)}
</Tag>
