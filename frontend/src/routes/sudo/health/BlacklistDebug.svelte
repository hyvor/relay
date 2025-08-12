<script lang="ts">
	import { Callout, Modal } from '@hyvor/design/components';
	import type { HealthCheckData } from '../sudoTypes';
	import BlacklistRow from './BlacklistRow.svelte';

	interface Props {
		data: HealthCheckData['none_of_the_ips_are_on_known_blacklists'];
	}

	let { data }: Props = $props();

	let show = $state(false);

	let hasErrors = $derived.by(() => {
		return Object.values(data.lists).some((ips) =>
			Object.values(ips).some((ip) => ip.status === 'error')
		);
	});
</script>

{#if hasErrors}
	<Callout type="warning" style="margin-top:10px; font-size: 14px;">
		Warning: there were some errors in the blacklist check. Please check debug information
		below.
	</Callout>
{/if}

<div class="wrap">
	<button onclick={() => (show = !show)}> Show blacklist debug information </button>
</div>

{#if show}
	<Modal
		bind:show
		title="Blacklists Check"
		size="large"
		footer={{ confirm: false, cancel: { text: 'Close' } }}
	>
		{#each Object.entries(data.lists) as [blacklist, ips]}
			<BlacklistRow blacklistId={blacklist} {ips} />
		{/each}
	</Modal>
{/if}

<style>
	.wrap {
		margin-top: 10px;
	}
	button {
		font-size: 14px;
		color: var(--text-light);
		text-decoration: underline;
	}
</style>
