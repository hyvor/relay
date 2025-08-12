<script lang="ts">
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import type { BlacklistIpResult } from '../sudoTypes';
	import { sudoConfigStore } from '../sudoStore';
	import { Link, SplitControl, Tag } from '@hyvor/design/components';
	import { slide } from 'svelte/transition';

	interface Props {
		blacklistId: string;
		ips: Record<string, BlacklistIpResult>;
	}

	let { blacklistId, ips }: Props = $props();

	let blacklist = $derived($sudoConfigStore.blacklists.find((b) => b.id === blacklistId));

	let status = $derived.by(() => {
		const ipResults = Object.values(ips);
		if (ipResults.some((ip) => ip.status === 'blocked')) return 'blocked';
		if (ipResults.some((ip) => ip.status === 'error')) return 'error';
		return 'ok';
	});

	let show = $state(false);
</script>

{#if blacklist}
	<button onclick={() => (show = !show)}>
		<div class="name">
			<span style="width:100px;display:inline-block">{blacklist.name}</span>
			<Tag
				size="small"
				color={status === 'ok' ? 'green' : status === 'blocked' ? 'red' : 'orange'}
			>
				{status === 'ok' ? 'OK' : status === 'blocked' ? 'Blocked' : 'Error'}
			</Tag>
		</div>
		<div class="right">
			<IconCaretDown size={14} />
		</div>
	</button>

	{#if show}
		<div class="data" transition:slide>
			<SplitControl label="DNSBL Lookup">
				{blacklist.dns_lookup_domain}
			</SplitControl>
			{#if blacklist.removal_url}
				<SplitControl label="Removal URL">
					<Link href={blacklist.removal_url} target="_blank" rel="noopener noreferrer">
						{blacklist.removal_url}
					</Link>
				</SplitControl>
			{/if}
			<SplitControl label="IPs">
				{#snippet nested()}
					<div class="ips-wrap">
						{#each Object.entries(ips) as [ip, result]}
							<div class="ip-row">
								<span style="width:150px;display: inline-block;">{ip}</span>
								<Tag
									size="small"
									color={result.status === 'blocked'
										? 'red'
										: result.status === 'error'
											? 'orange'
											: 'green'}
								>
									{#if result.status === 'blocked'}
										Blocked
									{:else if result.status === 'error'}
										Error
									{:else}
										OK
									{/if}
								</Tag>

								{#if result.error}
									<div class="ip-error">
										{result.error}
									</div>
								{/if}
							</div>
						{/each}
					</div>
				{/snippet}
			</SplitControl>
		</div>
	{/if}
{/if}

<style>
	button {
		display: flex;
		width: 100%;
		padding: 15px 25px;
		background-color: var(--input);
		border-radius: 20px;
		text-align: left;
		margin-bottom: 10px;
	}

	.name {
		flex: 1;
	}

	.data {
		padding-left: 25px;
		margin-bottom: 20px;
	}

	.ip-row {
		padding: 8px 0;
		width: 100%;
	}

	.ips-wrap {
		max-height: 300px;
		overflow-y: auto;
	}

	.ip-error {
		color: var(--red-dark);
		font-size: 12px;
		margin-top: 5px;
	}
</style>
