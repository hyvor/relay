<script lang="ts">
	import { page } from '$app/state';
	import { Base, HyvorBar, NavLink } from '@hyvor/design/components';
	import IconHdd from '@hyvor/icons/IconHdd';
	import IconSegmentedNav from '@hyvor/icons/IconSegmentedNav';
	import IconActivity from '@hyvor/icons/IconActivity';
	import relativeTime from 'dayjs/plugin/relativeTime';
	import dayjs from 'dayjs';
	import InstanceDomain from './InstanceDomain.svelte';

	dayjs.extend(relativeTime);

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<svelte:head>
	<title>Hyvor Relay · Sudo</title>
</svelte:head>

<Base>
	<main>
		<HyvorBar product="core" instance="https://hyvor.dev" config={{ name: 'Hyvor Relay' }} />

		<div id="wrap">
			<nav>
				<div class="hds-box nav-inner">
					<InstanceDomain />

					<div class="nav-title">Infrastructure</div>

					<NavLink href="/sudo/health" active={page.url.pathname === '/sudo/health'}>
						{#snippet start()}
							<IconActivity />
						{/snippet}
						Health
					</NavLink>

					<NavLink href="/sudo/servers" active={page.url.pathname === '/sudo/servers'}>
						{#snippet start()}
							<IconHdd />
						{/snippet}
						Servers
					</NavLink>
					<NavLink href="/sudo/queues" active={page.url.pathname === '/sudo/queues'}>
						{#snippet start()}
							<IconSegmentedNav />
						{/snippet}
						Queues
					</NavLink>

					<!-- <div class="nav-title">Users</div>

					<NavLink href="/sudo/projects" active={page.url.pathname === '/sudo/projects'}>
						{#snippet start()}
							<IconCardList />
						{/snippet}
						Projects
					</NavLink>

					<NavLink href="/sudo/domains" active={page.url.pathname === '/sudo/domains'}>
						{#snippet start()}
							<IconDatabase />
						{/snippet}
						Domains
					</NavLink>

					<NavLink href="/sudo/emails" active={page.url.pathname === '/sudo/emails'}>
						{#snippet start()}
							<IconEnvelope />
						{/snippet}
						Emails
					</NavLink> -->
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
		padding: 15px 30px;
		font-size: 14px;
		margin-top: 5px;
		color: var(--text-light);
		display: none;
	}
	.content {
		flex: 1;
		overflow: auto;
		padding: 15px;
	}
</style>
