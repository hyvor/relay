<script lang="ts">
	import { page } from '$app/state';
	import { NavLink } from '@hyvor/design/components';
	import IconHandThumbsDown from '@hyvor/icons/IconHandThumbsDown';
	import IconSearch from '@hyvor/icons/IconSearch';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	const prefix = `/sudo/debug`;
</script>

<div class="debug">
	<div class="nav hds-box">
		<NavLink href="/sudo/debug/bounces" active={page.url.pathname === '/sudo/debug/bounces'}>
			{#snippet start()}
				<IconHandThumbsDown />
			{/snippet}
			Bounce/FBL Logs
		</NavLink>
		<NavLink
			href="/sudo/debug/bounces-parser"
			active={page.url.pathname === '/sudo/debug/bounces-parser'}
		>
			{#snippet start()}
				<IconSearch />
			{/snippet}
			Bounce/FBL Parser
		</NavLink>
	</div>

	<div class="content hds-box">
		{@render children?.()}
	</div>
</div>

<style>
	.debug {
		display: flex;
		height: 100%;
	}
	.nav {
		width: 315px;
		margin-right: 15px;
		display: flex;
		flex-direction: column;
		flex-shrink: 0;
		height: 100%;
		padding: 25px 0;
		overflow: auto;
	}
	.nav :global(a.active) {
		background-color: var(--accent-light-mid);
	}
	.content {
		flex: 1;
		min-width: 0;
		height: 100%;
		display: flex;
		flex-direction: column;
	}
	.section-div {
		height: 25px;
		flex-shrink: 0;
	}

	@media (max-width: 992px) {
		.debug {
			flex-direction: column;
		}
		.nav {
			width: 100%;
			margin-right: 0;
			margin-bottom: 20px;
		}
	}
</style>
