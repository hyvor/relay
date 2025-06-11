<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { Server } from '../adminTypes';

	interface Props {
		server: Server;
	}

	let { server }: Props = $props();
</script>

<div class="row">
	<div class="id">
		({server.id})
	</div>
	<div class="hostname">
		{server.hostname}
	</div>
	<div class="work">
		<Tag size="small">API</Tag>
		<Tag size="small">Email</Tag>
		<Tag size="small">Webhook</Tag>
	</div>
</div>

<style>
	.row {
		display: flex;
		align-items: center;
		padding: 15px 25px;
		cursor: pointer;
		border-radius: 20px;
	}
	.row:hover {
		background-color: var(--hover);
	}
</style>
