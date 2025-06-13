<script lang="ts">
	import { page } from '$app/state';
	import { Base, HyvorBar, NavLink } from '@hyvor/design/components';
	import IconHdd from '@hyvor/icons/IconHdd';
	import IconSegmentedNav from '@hyvor/icons/IconSegmentedNav';
	import IconCardList from '@hyvor/icons/IconCardList';
	import IconDatabase from '@hyvor/icons/IconDatabase';
	import IconEnvelope from '@hyvor/icons/IconEnvelope';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<svelte:head>
	<title>Hyvor Relay · Admin</title>
</svelte:head>

<Base>
	<main>
		<HyvorBar product="core" instance="https://hyvor.dev" config={{ name: 'Hyvor Relay' }} />

		<div id="wrap">
			<nav>
				<div class="hds-box nav-inner">
					<div class="nav-title">Infrastructure</div>

					<NavLink href="/admin/servers" active={page.url.pathname === '/admin/servers'}>
						{#snippet start()}
							<IconHdd />
						{/snippet}
						Servers
					</NavLink>
					<NavLink href="/admin/queues" active={page.url.pathname === '/admin/queues'}>
						{#snippet start()}
							<IconSegmentedNav />
						{/snippet}
						Queues
					</NavLink>

					<div class="nav-title">Users</div>

					<NavLink href="/admin/projects" active={page.url.pathname === '/admin/projects'}>
						{#snippet start()}
							<IconCardList />
						{/snippet}
						Projects
					</NavLink>

					<NavLink href="/admin/domains" active={page.url.pathname === '/admin/domains'}>
						{#snippet start()}
							<IconDatabase />
						{/snippet}
						Domains
					</NavLink>

					<NavLink href="/admin/emails" active={page.url.pathname === '/admin/emails'}>
						{#snippet start()}
							<IconEnvelope />
						{/snippet}
						Emails
					</NavLink>
				</div>
			</nav>

			<div class="content">
				{@render children?.()}
				<div class="content-inner hds-box"></div>
			</div>
		</div>
	</main>
</Base>

<style>
	#wrap {
		display: flex;
		flex-direction: row;
		height: 100%;
	}
	main {
		display: flex;
		flex-direction: column;
		height: 100vh;
	}
	nav {
		width: 280px;
		padding: 15px;
		padding-right: 0;
		height: 100%;
	}
	.nav-inner {
		padding: 15px 0;
	}
	.nav-title {
		font-weight: 600;
		padding: 15px 30px;
		font-size: 14px;
		margin-top: 5px;
	}
	.content {
		flex: 1;
		overflow: auto;
		padding: 15px;
	}
</style>
