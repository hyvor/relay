<script lang="ts">
	import { TableRow, Tag } from '@hyvor/design/components';
	import type { DefaultDnsRecord } from '../../sudoTypes';

	interface Props {
		record: DefaultDnsRecord;
	}

	let { record }: Props = $props();
	let expanded = $state(false);

	function toggleExpanded() {
		expanded = !expanded;
	}

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			toggleExpanded();
		}
	}

	function truncateText(text: string, maxLength: number = 50): string {
		if (text.length <= maxLength) return text;
		return text.substring(0, maxLength) + '...';
	}
</script>

<TableRow>
	<div class="type">{record.type}</div>
	<div class="host">
		{record.host}

		{#if record.type === 'MX'}
			<Tag size="small">{record.priority}</Tag>
		{/if}
	</div>
	{#if record.content.length > 50}
		<div 
			class="content clickable" 
			onclick={toggleExpanded}
			onkeydown={handleKeydown}
			role="button"
			tabindex="0"
			aria-expanded={expanded}
			aria-label={`DNS record content: ${expanded ? 'expanded' : 'collapsed'}. Click to ${expanded ? 'collapse' : 'expand'}`}
		>
			{expanded ? record.content : truncateText(record.content)}
		</div>
	{:else}
		<div class="content">
			{record.content}
		</div>
	{/if}
	<div class="ttl">{record.ttl} seconds</div>
</TableRow>

<style>
	.content {
		word-break: break-all;
	}
    .host {
        word-break: break-all;  
    }
	.content.clickable {
		cursor: pointer;
		color: var(--color-primary);
		text-decoration: underline;
		text-decoration-style: dotted;
	}
	.content.clickable:hover,
	.content.clickable:focus {
		color: var(--color-primary-dark);
		outline: 2px solid var(--color-primary);
		outline-offset: 2px;
	}
	.readonly {
		color: var(--color-text-light);
		font-size: 12px;
		font-style: italic;
	}
</style>