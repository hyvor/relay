<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { SendStatus } from '../../types';

	interface Props {
		status: SendStatus;
	}

	let { status }: Props = $props();

	let color: 'green' | 'red' | 'default' = $derived.by(() => {
		if (status === 'accepted') {
			return 'green';
		} else if (status === 'bounced') {
			return 'red';
		} else if (status === 'complained') {
			return 'red';
		} else {
			return 'default';
		}
	});
</script>

<Tag {color}>
	{status.charAt(0).toUpperCase() + status.slice(1)}
</Tag>
