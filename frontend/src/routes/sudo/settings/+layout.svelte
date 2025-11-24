<script lang="ts">
	import { page } from '$app/state';
	import { NavLink } from '@hyvor/design/components';
	import IconCardText from '@hyvor/icons/IconCardText';
	import IconGlobe2 from '@hyvor/icons/IconGlobe';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	const prefix = `/sudo/settings`;
</script>

<div class="settings">
	<div class="nav hds-box">
		<NavLink href={prefix} active={page.url.pathname === prefix}>
			{#snippet start()}
				<IconCardText />
			{/snippet}
			Instance
		</NavLink>

		<NavLink href="/sudo/settings/dns" active={page.url.pathname === '/sudo/settings/dns'}>
			{#snippet start()}
				<IconGlobe2 />
			{/snippet}
			DNS
		</NavLink>

		<div class="section-div"></div>
	</div>

	<div class="content hds-box">
		{@render children?.()}
	</div>
</div>

<style>
	.settings {
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
		background-color: var(--accent-light) !important;
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
		.settings {
			flex-direction: column;
		}
		.nav {
			width: 100%;
			margin-right: 0;
			margin-bottom: 20px;
		}
	}
</style>
