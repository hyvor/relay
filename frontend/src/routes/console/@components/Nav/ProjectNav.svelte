<script lang="ts">
	import { NavLink } from '@hyvor/design/components';
	import IconChevronExpand from '@hyvor/icons/IconChevronExpand';
	import IconSend from '@hyvor/icons/IconSend';
	import IconGear from '@hyvor/icons/IconGear';
	import IconKey from '@hyvor/icons/IconKey';
	import IconGraphUp from '@hyvor/icons/IconGraphUp';
	import NavItem from './NavItem.svelte';
	import { page } from '$app/state';
	import IconEnvelope from '@hyvor/icons/IconEnvelope';
	import { selectingProject } from '../../lib/stores/consoleStore';
	import { projectStore } from '../../lib/stores/projectStore';
	import IconSlashCircle from '@hyvor/icons/IconSlashCircle';
	import IconDatabase from '@hyvor/icons/IconDatabase';
	import ProjectSendTypeTag from './ProjectSendTypeTag.svelte';

	let width: number;

	function triggerProjectSelection() {
		console.log('triggerProjectSelection');
		selectingProject.set(true);
	}
</script>

<svelte:window bind:innerWidth={width} />

<div class="wrap hds-box">
	<button class="current" on:click={triggerProjectSelection}>
		<div class="left">
			<div class="name">
				{$projectStore.name}
				<span
					class="dot"
					class:distributional={$projectStore.send_type === 'distributional'}
				></span>
			</div>
		</div>
		<IconChevronExpand />
	</button>

	<div class="nav-links">
		<NavLink
			href={'/console/' + $projectStore.id.toString()}
			active={page.url.pathname === `/console/${$projectStore.id}`}
		>
			<NavItem>
				<IconGraphUp slot="icon" />
				<span slot="text">Overview</span>
			</NavItem>
		</NavLink>

		<NavLink
			href={'/console/' + $projectStore.id.toString() + '/sends'}
			active={page.url.pathname.startsWith(`/console/${$projectStore.id}/sends`)}
		>
			<NavItem>
				<IconEnvelope slot="icon" />
				<span slot="text">Sends</span>
			</NavItem>
		</NavLink>

		<NavLink
			href={'/console/' + $projectStore.id.toString() + '/domains'}
			active={page.url.pathname.startsWith(`/console/${$projectStore.id}/domains`)}
		>
			<NavItem>
				<IconDatabase slot="icon" />
				<span slot="text">Domains</span>
			</NavItem>
		</NavLink>

		<NavLink
			href={'/console/' + $projectStore.id.toString() + '/api'}
			active={page.url.pathname.startsWith(`/console/${$projectStore.id}/api`)}
		>
			<NavItem>
				<IconKey slot="icon" />
				<span slot="text">API</span>
			</NavItem>
		</NavLink>

		<NavLink
			href={'/console/' + $projectStore.id.toString() + '/webhooks'}
			active={page.url.pathname.startsWith(`/console/${$projectStore.id}/webhooks`)}
		>
			<NavItem>
				<IconSend slot="icon" />
				<span slot="text">Webhooks</span>
			</NavItem>
		</NavLink>

		<NavLink
			href={'/console/' + $projectStore.id.toString() + '/suppressions'}
			active={page.url.pathname.startsWith(`/console/${$projectStore.id}/suppressions`)}
		>
			<NavItem>
				<IconSlashCircle slot="icon" />
				<span slot="text">Suppressions</span>
			</NavItem>
		</NavLink>

		<NavLink
			href={'/console/' + $projectStore.id.toString() + '/settings'}
			active={page.url.pathname.startsWith(`/console/${$projectStore.id}/settings`)}
		>
			<NavItem>
				<IconGear slot="icon" />
				<span slot="text">Settings</span>
			</NavItem>
		</NavLink>
	</div>
</div>

<style lang="scss">
	.wrap {
		padding-bottom: 15px;
		padding-top: 5px;
	}
	.current {
		margin: 10px;
		display: flex;
		align-items: center;
		text-align: left;
		width: calc(100% - 20px);
		padding: 10px 20px;
		border-radius: var(--box-radius);
		cursor: pointer;
		.left {
			flex: 1;
		}
		.name {
			font-weight: 600;
		}
		&:hover {
			background-color: var(--hover);
		}
	}

	.name {
		display: inline-flex;
		align-items: center;
		gap: 5px;
	}

	.dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		display: inline-block;
		margin-left: 5px;
		background-color: var(--blue-light);
		&.distributional {
			background-color: var(--orange-light);
		}
	}

	@media (max-width: 992px) {
		.wrap {
			width: 100%;
			z-index: 100;
			border-radius: 0 !important;
			padding-top: 5px;
			padding-bottom: 0;
		}
		.nav-links {
			display: flex;
			border-top: 1px solid var(--border);
			overflow-x: auto;

			:global(a .middle) {
				display: none;
			}
			:global(a .start) {
				margin-right: 0 !important;
			}
			:global(a) {
				border-left: none !important;
				border-top: 3px solid transparent;
				flex: 1;
				justify-content: center;
			}
			:global(a.active) {
				border-top-color: var(--accent);
			}
			:global(.line) {
				display: none !important;
			}
		}
		.current {
			margin: 0px auto;
			margin-bottom: 5px;
		}
		.current .left {
			display: flex;
			gap: 10px;
			align-items: center;
		}
	}
</style>
