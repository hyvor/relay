<script lang="ts">
	import { page } from '$app/state';
	import SingleBox from '../../SingleBox.svelte';
	import SendDetail from '$lib/sends/SendDetail.svelte';
	import { getSendByUuid } from '../../sudoActions';
	import type { Send } from '$lib/sends/types';
</script>

<SingleBox>
	<SendDetail
		fetchSend={() => getSendByUuid(page.params.uuid ?? '')}
		backHref="/sudo/sends"
		{headerExtra}
	/>
</SingleBox>

{#snippet headerExtra(send: Send)}
	{#if send.project}
		<a class="project-link" href="/sudo/sends?project_id={send.project.id}">
			{send.project.name}
			<span class="muted">#{send.project.id}</span>
		</a>
	{/if}
{/snippet}

<style>
	.project-link {
		color: inherit;
		text-decoration: none;
		font-size: 14px;
	}
	.project-link:hover {
		text-decoration: underline;
	}
	.muted {
		color: var(--text-light);
		margin-left: 4px;
	}
</style>
