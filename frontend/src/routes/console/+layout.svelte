<script lang="ts">
	import { Loader, toast } from '@hyvor/design/components';
	import { onMount } from 'svelte';
	import type { AppConfig, ProjectUser } from './types';
	import consoleApi from './lib/consoleApi.svelte';
	import { authOrganizationStore, getAppConfig, setAppConfig } from './lib/stores/consoleStore';
	import { setCurrentProjectUser, setProjectUsers } from './lib/stores/projectStore.svelte';
	import { page } from '$app/state';
	import { CloudContext, type CloudContextOrganization, type CloudContextUser, HyvorBar } from '@hyvor/design/cloud';
	import { goto } from '$app/navigation';
	import {get} from "svelte/store";

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

	interface InitResponse {
		config: AppConfig;
		project_users: ProjectUser[];
		organization: CloudContextOrganization;
	}

	let isLoading = $state(true);

	function startConsole(switchingOrg = false) {
		isLoading = true;

		consoleApi
			.get<InitResponse>({
				userApi: true,
				endpoint: 'init',
			})
			.then((res) => {
				setAppConfig(res.config);
	 			setProjectUsers(res.project_users);
				authOrganizationStore.set(res.organization);

				function getProjectId(): number | undefined {
					const projectId = page.params.id;
					return projectId ? Number(projectId) : res.project_users[0]?.project.id;
				}

				const projectId = getProjectId();
				const userProject = res.project_users.find((pu) => pu.project.id === projectId);

				if (userProject) {
					setCurrentProjectUser(userProject);
				} else {
					goto('/console/new');
				}

				if (switchingOrg && !page.url.pathname.startsWith('/console/new')) {
					goto('/console');
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
	}

	onMount(startConsole);
</script>

<svelte:head>
	<title>Console | Hyvor Relay</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<main>
	{#if isLoading}
		<div class="full-loader">
			<Loader size="large"></Loader>
		</div>
	{:else}
		<CloudContext
			context={{
				component: "relay",
				deployment: "cloud",
				instance: getAppConfig().hyvor.instance,
				license: {
					type: 'none',
					subscription: null,
					license: null,
					trial_ends_at: null
				}, // TODO
				organization: get(authOrganizationStore),
				user: getAppConfig().user,
				callbacks: {
					onOrganizationSwitch: (switcher) => {
						isLoading = true;

						switcher
							.then((_org) => {
								startConsole(true);
							})
							.catch(() => {
								isLoading = false;
							});
					}
				}
			}}
		>
			<HyvorBar />
			{@render children?.()}
		</CloudContext>
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
