<script lang="ts">
	import { HyvorBar, Loader, toast } from '@hyvor/design/components';
	import { onMount } from 'svelte';
	import type { AppConfig, Project } from './types';
	import consoleApi from './lib/consoleApi';
	import { userProjectStore } from './lib/stores/userProjectStore';
	import { projectStore } from './lib/stores/projectStore';
	import { page } from '$app/stores';
	import { getAppConfig, setAppConfig } from './lib/stores/consoleStore';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	interface InitResponse {
		config: AppConfig;
		projects: Project[];
	}

	let isLoading = $state(true);

	onMount(() => {
		consoleApi
			.get<InitResponse>({
				userApi: true,
				endpoint: 'init'
			})
			.then((res) => {
				setAppConfig(res.config);
				userProjectStore.set(res.projects);
				if (res.projects.length != 0) {
					projectStore.set(res.projects[0]);
				}
				isLoading = false;
			})
			.catch((err) => {
				if (err.code === 401) {
					const toPage = $page.url.searchParams.has('signup') ? 'signup' : 'login';
					const url = new URL(err.data[toPage + '_url'], location.origin);
					url.searchParams.set('redirect', location.href);
					location.href = url.toString();
				} else {
					toast.error(err.message);
				}
			});
	});
</script>

<svelte:head>
	<title>Console · Hyvor Relay</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<main>
	{#if isLoading}
		<div class="full-loader">
			<Loader size="large"></Loader>
		</div>
	{:else}
		<HyvorBar
			product="core"
			instance={getAppConfig().hyvor.instance}
			config={{ name: 'Hyvor Relay' }}
		/>
		{@render children?.()}
	{/if}
</main>

<style>
	main {
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100vh;
	}

	.full-loader {
		width: 100%;
		height: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
	}
</style>
