<script lang="ts">
	import { HyvorBar, Loader, toast } from '@hyvor/design/components';
	import { onMount } from 'svelte';
	import type { AppConfig, ProjectUser } from './types';
	import consoleApi from './lib/consoleApi.svelte';
	import { getAppConfig, setAppConfig } from './lib/stores/consoleStore';
	import { setCurrentProjectUser, setProjectUsers } from './lib/stores/projectStore.svelte';
	import { page } from '$app/state';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	interface InitResponse {
		config: AppConfig;
		project_users: ProjectUser[];
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
				setProjectUsers(res.project_users);

				function getProjectId(): number | undefined {
					const projectId = page.params.id;
					return projectId ? Number(projectId) : res.project_users[0]?.project.id;
				}

				const projectId = getProjectId();
				const userProject = res.project_users.find((pu) => pu.project.id === projectId);

				if (userProject) {
					setCurrentProjectUser(userProject);
				}

				isLoading = false;
			})
			.catch((err) => {
				if (err.code === 401) {
					const toPage = page.url.searchParams.has('signup') ? 'signup' : 'login';
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
			logo="/img/logo.svg"
			instance={getAppConfig().hyvor.instance}
			config={{ name: 'Hyvor Relay' }}
			cloud={getAppConfig().hosting === 'cloud'}
			authOverride={{
				user: getAppConfig().user,
				logoutUrl: '/api/oidc/logout'
			}}
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
