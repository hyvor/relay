<script lang="ts">
	import type { Queue } from '../adminTypes';

	interface Props {
		queue: Queue;
	}

	let { queue }: Props = $props();
</script>

<div class="queue">
	<div class="id">
		({queue.id})
	</div>
	<div class="name">
		{queue.name}
	</div>
</div>
