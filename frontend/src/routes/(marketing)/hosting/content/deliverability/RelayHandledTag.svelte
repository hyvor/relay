<script lang="ts">
	import { Tag } from '@hyvor/design/components';

	interface Props {
		size?: 'small' | 'x-small';
	}

	let { size = 'x-small' }: Props = $props();
</script>

<span style="line-height: 1.2;margin-right:3px;">
	<Tag {size} color="green" outline>Managed</Tag>
</span>
