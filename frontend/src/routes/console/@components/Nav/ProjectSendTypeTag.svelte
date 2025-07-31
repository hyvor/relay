<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { ProjectSendType } from '../../types';

	interface Props {
		type: ProjectSendType;
		size?: 'x-small' | 'small' | 'medium';
	}

	let { type, size = 'medium' }: Props = $props();
</script>

{#if type === 'transactional'}
	<Tag color="blue" {size}>Transactional</Tag>
{:else}
	<Tag color="orange" {size}>Distributional</Tag>
{/if}
