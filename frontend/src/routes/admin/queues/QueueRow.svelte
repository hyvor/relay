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

<style>
	.queue {
		display: flex;
		align-items: center;
		padding: 8px 20px;
	}
	.id {
		margin-right: 10px;
		color: var(--text-light);
		font-size: 14px;
	}
</style>
