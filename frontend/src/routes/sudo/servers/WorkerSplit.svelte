<script lang="ts">
	import { Button, SplitControl, TextInput, toast } from '@hyvor/design/components';
	import type { Server } from '../sudoTypes';
	import { updateServer } from '../sudoActions';

	interface Props {
		worker: 'api' | 'email' | 'webhook';
		server: Server;
	}

	let { worker, server }: Props = $props();

	let initialValue = $derived(server[`${worker}_workers`]);
	let value = $state(server[`${worker}_workers`]);

	let saving = $state(false);

	function getWorkerName() {
		return {
			api: 'API Workers',
			email: 'Email Workers per IP',
			webhook: 'Webhook Workers'
		}[worker];
	}

	function getTipText() {
		return {
			api: 'Each worker can consume around 5MB of memory. Scale based on your API load.',
			email: 'Number of Go workers sending emails per IP. Default is 4.',
			webhook: 'Number of Go workers processing webhooks. Default is 2.'
		}[worker];
	}

	function save() {
		saving = true;

		updateServer(server.id, {
			[`${worker}_workers`]: value
		})
			.then(() => {
				toast.success('Workers updated successfully.');
			})
			.catch(() => {
				toast.error('Failed to update workers. Please try again.');
			})
			.finally(() => {
				saving = false;
			});
	}
</script>

<SplitControl label={getWorkerName()}>
	<div class="input-wrap">
		<TextInput bind:value type="number" min={0} block />
		<Button onclick={save} disabled={initialValue === value}>Save</Button>
	</div>

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
	.input-wrap {
		display: flex;
		gap: 8px;
		align-items: center;
	}
</style>
