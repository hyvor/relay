<script lang="ts">
	import { Button, Tag, Tooltip } from '@hyvor/design/components';
	import type { IpAddress } from '../sudoTypes';
	import IconExclamationCircle from '@hyvor/icons/IconExclamationCircle';

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
			<Tooltip
				text="This IP address will not be used for email delivery until you assign a queue to it."
			>
				<span class="none">
					None
					<IconExclamationCircle size={14} />
				</span>
			</Tooltip>
		{/if}

		<Button size="x-small" color="input" style="margin-left: 5px;">
			{ip.email_queue ? 'Change' : 'Assign'}
		</Button>
	</td>
	<td> {ip.ptr} </td>
	<td class="">
		<Tag size="small" color="green">Enabled</Tag>
	</td>
</tr>

<style>
	td {
		padding: 0.75rem;
		text-align: left;
	}
	.none {
		color: var(--orange-dark);
		font-size: 14px;
		display: inline-flex;
		align-items: center;
		gap: 5px;
	}

	.assign-button {
		font-size: 14px;
		margin-left: 5px;
	}
</style>
