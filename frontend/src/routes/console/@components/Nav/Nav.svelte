<script>
	import ProjectNav from './ProjectNav.svelte';
</script>

<div id="nav">
	<div id="nav-wrap">
		<div class="project-nav">
			<ProjectNav />
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
