<script lang="ts">
	import { IconButton } from '@hyvor/design/components';
	import { cidrAddressCount } from './allowedIp';
	import IconX from '@hyvor/icons/IconX';

	interface Props {
		index: number;
		entry: string;
		onremove: (entry: string) => void;
	}

	let { index, entry, onremove }: Props = $props();

	let addressCount = $derived(cidrAddressCount(entry));
</script>

<div class="ip-row">
	<div class="address">
		{entry}
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

	.count {
		font-size: 14px;
		color: var(--text-light);
	}
</style>
