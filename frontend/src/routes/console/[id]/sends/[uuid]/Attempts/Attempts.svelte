<script lang="ts">
	import type { Send } from '../../../../types';
	import AttemptRow from './AttemptRow.svelte';

	interface Props {
		send: Send;
	}

	let { send }: Props = $props();
</script>

<div class="attempts">
	<div class="title">Attempts</div>

	{#if send.attempts.length}
		<div class="rows">
			{#each send.attempts as attempt}
				<AttemptRow {attempt} />
			{/each}
		</div>
	{/if}
</div>

<style>
	.attempts {
		border-top: 1px solid var(--border);
		padding: 20px 30px;
	}
	.title {
		font-size: 18px;
		font-weight: bold;
		margin-bottom: 20px;
	}
	.rows {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
</style>
