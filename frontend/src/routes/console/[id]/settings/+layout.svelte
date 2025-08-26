<script lang="ts">
	import { page } from '$app/state';
	import { NavLink } from '@hyvor/design/components';
	import IconCardText from '@hyvor/icons/IconCardText';
	import IconPeople from '@hyvor/icons/IconPeople';
	import { getCurrentProject } from '../../lib/stores/projectStore.svelte';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	let project = getCurrentProject();
	const prefix = `/console/${project.id}/settings`;
</script>

<div class="settings">
	<div class="nav hds-box">
		<NavLink href={prefix} active={page.url.pathname === prefix}>
			{#snippet start()}
				<IconCardText />
			{/snippet}
			Project
		</NavLink>

		<NavLink href={`${prefix}/users`} active={page.url.pathname === `${prefix}/users`}>
			{#snippet start()}
				<IconPeople />
			{/snippet}
			Users
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
