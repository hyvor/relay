<script lang="ts">
	import { SplitControl, TextInput } from '@hyvor/design/components';
	import type { Server } from '../adminTypes';

	interface Props {
		worker: 'api' | 'email' | 'webhook';
		server: Server;
	}

	let { worker, server }: Props = $props();

	let value = $state(server[`${worker}_workers`]);

	function getWorkerName() {
		return {
			api: 'API',
			email: 'Email',
			webhook: 'Webhook'
		}[worker];
	}

	function getTipText() {
		return {
			api: 'Each worker can consume around 5MB of memory. Scale based on your API load. Default is CPU cores * 2.',
			email: 'Number of Go workers senidng emails from the queue.',
			webhook: ''
		}[worker];
	}
</script>

<SplitControl label={getWorkerName()}>
	<TextInput bind:value type="number" min={0} block />

	<div class="tip">
		{getTipText()}
	</div>
</SplitControl>

<style>
	.tip {
		margin-top: 8px;
		font-size: 14px;
		color: var(--text-light);
	}
</style>
