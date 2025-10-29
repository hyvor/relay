<script lang="ts">
	import type { InfrastructureBounce } from '../../sudoTypes';
	import {
		getInfrastructureBounces,
		markAllInfrastructureBouncesAsRead
	} from '../../sudoActions';
	import { IconMessage, toast, Button, LoadButton, TabNav, TabNavItem } from '@hyvor/design/components';
	import InfrastructureBounceRow from './InfrastructureBounceRow.svelte';

	let bounces: InfrastructureBounce[] = $state([]);
	let offset = 0;
	let loading = $state(false);
	let hasMore = $state(true);
	let markingAll = $state(false);
	const limit = 20;

	type FilterType = 'all' | 'unread' | 'read';
	let filter: FilterType = $state('all');

	function loadBounces(more = false) {
		if (loading) return;

		loading = true;
		const currentOffset = more ? offset : 0;

		const isReadFilter = filter === 'all' ? undefined : filter === 'read';

		getInfrastructureBounces(limit, currentOffset, isReadFilter)
			.then((data) => {
				if (more) {
					bounces = [...bounces, ...data];
				} else {
					bounces = data;
					offset = 0;
				}
				offset = currentOffset + data.length;
				hasMore = data.length === limit;
			})
			.catch((error) => {
				toast.error(error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	$effect(() => {
		loadBounces(false);
	});

	async function handleMarkAllAsRead() {
		if (markingAll) return;
		markingAll = true;
		try {
			const result = await markAllInfrastructureBouncesAsRead();
			toast.success(`Marked ${result.marked_count} bounce(s) as read`);
			loadBounces(false);
		} catch (error: any) {
			toast.error('Failed to mark all as read: ' + error.message);
		} finally {
			markingAll = false;
		}
	}

	function handleBounceMarkedAsRead(id: number) {
		// Update the bounce in the list
		const bounceIndex = bounces.findIndex((b) => b.id === id);
		if (bounceIndex !== -1) {
			bounces[bounceIndex].is_read = true;
			bounces[bounceIndex].updated_at = Math.floor(Date.now() / 1000);
		}
	}

</script>

<div class="infrastructure-bounces">
	<div class="header">
		<Button color="accent" size="small" onclick={handleMarkAllAsRead} disabled={markingAll}>
			{markingAll ? 'Marking...' : 'Mark All as Read'}
		</Button>
	</div>

	<div class="tabs">
		<TabNav bind:active={filter}>
			<TabNavItem name="all">All</TabNavItem>
			<TabNavItem name="unread">Unread</TabNavItem>
			<TabNavItem name="read">Read</TabNavItem>
		</TabNav>
	</div>

	{#if bounces.length === 0 && !loading}
		<IconMessage empty message="No infrastructure bounces found" />
	{:else}
		<div class="rows">
			{#each bounces as bounce (bounce.id)}
				<InfrastructureBounceRow {bounce} onMarkAsRead={handleBounceMarkedAsRead} />
			{/each}
		</div>

		{#if hasMore}
			<div class="load-more">
				<LoadButton text="Load More" {loading} show={hasMore} on:click={() => loadBounces(true)} />
			</div>
		{/if}
	{/if}
</div>

<style>
	.infrastructure-bounces {
		height: 100%;
		display: flex;
		flex-direction: column;
		overflow: auto;
	}

	.header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
		gap: 20px;
	}

	.tabs {
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}

	.rows {
		padding: 0 30px;
		margin-top: 20px;
	}

	.load-more {
		margin: 20px 0;
		text-align: center;
	}
</style>

