<script lang="ts">
	import type { SendRecipient } from '../../types';
	import RecipientStatus from './RecipientStatus.svelte';

	interface Props {
		recipients: SendRecipient[];
	}

	let { recipients }: Props = $props();

	let statuses = $derived.by(() => {
		const statusCount: Record<string, number> = {};
		recipients.forEach((recipient) => {
			if (recipient.status) {
				statusCount[recipient.status] = (statusCount[recipient.status] || 0) + 1;
			}
		});
		return statusCount;
	});
</script>

{#each Object.entries(statuses) as [status, count]}
	<RecipientStatus status={status as any} num={count} />
{/each}
