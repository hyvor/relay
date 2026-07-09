<script>
	import AccountNav from './AccountNav.svelte';
	import ProjectNav from './ProjectNav.svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';
</script>

<div id="nav">
	<div id="nav-wrap">
		<div class="nav-inner">
			<AccountNav />
			<div class="project-nav">
				<ProjectNav />
			</div>
		</div>
		<div class="footer">
			{#if getAppConfig().user.is_sudo}
				<a href="/sudo" class="console-link">sudo</a>
			{/if}
		</div>
	</div>
</div>

<style>
	#nav {
		width: 280px;
		padding: 15px;
		padding-right: 0;
		flex-shrink: 0;
	}

	#nav-wrap {
		display: flex;
		flex-direction: column;
		height: 100%;
	}

	.nav-inner {
		flex: 1;
	}

	.footer {
		padding: 15px;
		font-size: 12px;
		color: var(--text-light);
		text-align: center;
	}

	.console-link:hover {
		text-decoration: underline;
	}

	@media (max-width: 992px) {
		#nav-wrap {
			position: fixed;
			bottom: 0;
			left: 0;
			width: 100%;
			z-index: 100;
			height: initial;
			flex: 1;
		}
		#nav {
			height: 0;
			padding: 0;
		}
	}
</style>
