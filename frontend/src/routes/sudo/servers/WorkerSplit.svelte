<script lang="ts">
	import { SplitControl, TextInput, toast } from '@hyvor/design/components';
	import type { Server } from '../sudoTypes';
	import { updateServer } from '../sudoActions';
	import { serversStore } from '../sudoStore';

	interface Props {
		worker: 'api' | 'email' | 'webhook';
		server: Server;
	}

	let { worker, server }: Props = $props();

	let value = $state(server[`${worker}_workers`]);

	$effect(() => {
		if (value === server[`${worker}_workers`]) return;

		const workerField = `${worker}_workers` as const;
		const updateData = { [workerField]: value };
		
		updateServer(server.id, updateData)
			.then((updatedServer) => {
				serversStore.update(servers => 
					servers.map(s => s.id === server.id ? updatedServer : s)
				);
				
				toast.success(`${getWorkerName()} updated successfully`);
			})
			.catch((error) => {
				toast.error(`Failed to update ${getWorkerName()}: ${error instanceof Error ? error.message : 'Unknown error'}`);
				value = server[`${worker}_workers`];
			});
	});

	function getWorkerName() {
		return {
			api: 'API Workers',
			email: 'Email Workers per IP',
			webhook: 'Webhook Workers'
		}[worker];
	}

	function getTipText() {
		return {
			api: 'Each worker can consume around 5MB of memory. Scale based on your API load. Default is CPU cores * 2.',
			email: 'Number of Go workers sending emails per IP. Default is 4.',
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
