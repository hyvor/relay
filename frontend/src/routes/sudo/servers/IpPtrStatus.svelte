<script lang="ts">
	import { Tag } from '@hyvor/design/components';

	interface Props {
		status: boolean;
		forward?: boolean;
	}

	let { status, forward = false }: Props = $props();

	let color: 'green' | 'red' = $derived(status ? 'green' : 'red');
	let statusText: string = $derived(status ? 'ok' : 'invalid');
</script>

<Tag size="small" {color}>{forward ? `Forward ${statusText}` : `Reverse ${statusText}`}</Tag>
