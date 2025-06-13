<script lang="ts">
	import { Tag, Tooltip } from '@hyvor/design/components';

	interface Props {
		text: string;
		value: number;
	}

	let { text, value }: Props = $props();
</script>

<Tooltip text="{text} Workers: {value}">
	<Tag size="small">{text}: {value}</Tag>
</Tooltip>
