<script lang="ts">
	import { IconButton, Tooltip } from '@hyvor/design/components';
	import { cidrAddressCount, isBroadAllowedIpEntry } from './allowedIp';
	import IconX from '@hyvor/icons/IconX';
	import IconExclamationTriangleFill from '@hyvor/icons/IconExclamationTriangleFill';

	interface Props {
		index: number;
		entry: string;
		onremove: (entry: string) => void;
	}

	let { index, entry, onremove }: Props = $props();

	let addressCount = $derived(cidrAddressCount(entry));
	let isBroad = $derived(isBroadAllowedIpEntry(entry));
</script>

<div class="ip-row">
	<div class="address">
		{entry}
		{#if isBroad}
			<Tooltip text="This range may be too broad." position="top">
				<IconExclamationTriangleFill size={12} style="color:var(--orange)" />
			</Tooltip>
		{/if}
	</div>
	<span class="count"
		>{addressCount.toLocaleString() + ' address' + (addressCount === 1 ? '' : 'es')}</span
	>
	<IconButton size={16} color="input" on:click={() => onremove(entry)}>
		<IconX size={10} />
	</IconButton>
</div>

<style>
	.ip-row {
		display: grid;
		align-items: center;
		grid-template-columns: 1fr 1fr auto;
		padding: 4px 8px;
		border-radius: 4px;
	}

	.address {
		display: flex;
		align-items: center;
		gap: 6px;
	}

	.count {
		font-size: 14px;
		color: var(--text-light);
	}
</style>
