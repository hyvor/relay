<script lang="ts">
	import { TableRow, Tag } from '@hyvor/design/components';
	import type { DefaultDnsRecord } from '../../sudoTypes';

	interface Props {
		record: DefaultDnsRecord;
	}

	let { record }: Props = $props();
</script>

<TableRow>
	<div class="type">{record.type}</div>
	<div class="host">
		{record.host}

		{#if record.type === 'MX'}
			<Tag size="small">{record.priority}</Tag>
		{/if}
	</div>
	<div class="content">{record.content}</div>
	<div class="ttl">{record.ttl} seconds</div>
	<div class="readonly">Read-only</div>
</TableRow>

<style>
	.content {
		word-break: break-all;
	}
	.readonly {
		color: var(--color-text-light);
		font-size: 12px;
		font-style: italic;
	}
</style>