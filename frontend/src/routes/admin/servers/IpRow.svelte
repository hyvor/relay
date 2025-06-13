<script lang="ts">
	import { Tag } from '@hyvor/design/components';
	import type { IpAddress } from '../adminTypes';

	interface Props {
		ip: IpAddress;
	}

	let { ip }: Props = $props();
</script>

<tr>
	<td class="ip-address">
		{ip.ip_address}
	</td>
	<td class="queue-name">
		{#if ip.email_queue}
			{ip.email_queue}
		{:else}
			None
		{/if}
	</td>
	<td> relay.hyvor.com </td>
	<td class="">
		<Tag size="small" color="green">Enabled</Tag>
	</td>
</tr>

<style>
	td {
		padding: 0.75rem;
		text-align: left;
	}
</style>
