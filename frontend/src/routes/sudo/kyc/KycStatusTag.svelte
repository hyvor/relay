<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { KycStatus } from '../sudoTypes';

	interface Props {
		status: KycStatus;
		size?: 'x-small' | 'small' | 'medium' | 'large';
	}

	let { status, size = 'small' }: Props = $props();

	const color = $derived.by(() => {
		switch (status) {
			case 'approved':
				return 'green';
			case 'rejected':
				return 'red';
			default:
				return 'orange';
		}
	});
</script>

<Tag {size} {color}>
	<span class="tag-content">
		{status}
	</span>
</Tag>

<style>
	.tag-content {
		text-transform: capitalize;
	}
</style>