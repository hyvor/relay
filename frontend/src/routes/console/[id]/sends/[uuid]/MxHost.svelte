<script lang="ts">
	import { Tooltip } from '@hyvor/design/components';
	import type { SmtpConversation } from '../../../types';

	interface Props {
		host: string;
		selected: boolean;
		index: number;
		conversation: SmtpConversation | null;
		onselect: (host: string) => void;
	}

	let { host, selected, index, conversation, onselect }: Props = $props();

	let status: 'accepted' | 'failed' | 'not_attempted' = $derived.by(() => {
		if (conversation === null) {
			return 'not_attempted';
		} else if (conversation.Error || conversation.SmtpErrorStatus) {
			return 'failed';
		} else {
			return 'accepted';
		}
	});

	function getTooltip() {
		return {
			accepted: 'Accepted by this host',
			failed: 'Failed to send to this host',
			not_attempted: 'Did not attempt to send to this host'
		}[status];
	}
</script>

<Tooltip text={getTooltip()}>
	<button
		class="mx-host"
		onclick={() => onselect(host)}
		class:accepted={status === 'accepted'}
		class:failed={status === 'failed'}
		class:no-attempt={status === 'not_attempted'}
		class:selected
		disabled={status === 'not_attempted'}
	>
		({index}) {host}
	</button>
</Tooltip>

<style>
	.mx-host {
		font-size: 14px;
		padding: 4px 10px;
		border-bottom: 2px solid transparent;
	}
	.mx-host:not(.no-attempt) {
		cursor: pointer;
	}
	.mx-host.accepted {
		color: var(--green-dark);
		--active-bg: var(--green-light);
	}
	.mx-host.failed {
		color: var(--red-dark);
		--active-bg: var(--red-light);
	}
	.mx-host.no-attempt {
		color: var(--text-light);
		cursor: default;
	}
	.mx-host.selected {
		border-bottom: 2px solid var(--active-bg);
	}
</style>
