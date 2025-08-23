<script lang="ts">
	import { toast, confirm } from '@hyvor/design/components';
	import SettingsBody from '../../../@components/content/SettingsBody.svelte';
	import ScopeMask from '../../../@components/Scope/ScopeMask.svelte';
	import InviteUserModal from './InviteUserModal.svelte';
	import UserSearch from './UserSearch.svelte';
	import UserList from './UserList.svelte';
	import consoleApi from '../../../lib/consoleApi.svelte';
	import { getAppConfig } from '../../../lib/stores/consoleStore';
	import { onMount } from 'svelte';
	import type { ProjectUserSearchResult, ProjectUser, Scope } from '../../../types';

	let searchEmail = $state('');
	let searchResults = $state<ProjectUserSearchResult[]>([]);
	let isSearching = $state(false);
	let hasSearched = $state(false);

	let projectUsers = $state<ProjectUser[]>([]);
	let isLoadingUsers = $state(true);

	let showInviteModal = $state(false);
	let selectedUser = $state<ProjectUserSearchResult | null>(null);
	let selectedScopes = $state<string[]>(['project.read']);
	let isInviting = $state(false);

	const availableScopes: Scope[] = [
		'project.read',
		'project.write',
		'sends.read',
		'sends.write',
		'sends.send',
		'domains.read',
		'domains.write',
		'webhooks.read',
		'webhooks.write',
		'api_keys.read',
		'api_keys.write',
		'suppressions.read',
		'suppressions.write',
		'analytics.read'
	];

	onMount(() => {
		loadProjectUsers();
	});

	async function loadProjectUsers() {
		try {
			isLoadingUsers = true;
			const users = await consoleApi.get<ProjectUser[]>({
				endpoint: 'project-users'
			});
			projectUsers = users;
		} catch (error: any) {
			toast.error('Failed to load project users: ' + error.message);
		} finally {
			isLoadingUsers = false;
		}
	}

	async function searchUsers() {
		if (!searchEmail.trim()) {
			searchResults = [];
			hasSearched = false;
			return;
		}

		try {
			isSearching = true;
			hasSearched = true;
			const results = await consoleApi.get<ProjectUserSearchResult[]>({
				endpoint: 'search-users',
				data: { email: searchEmail.trim() }
			});
			searchResults = results;
		} catch (error: any) {
			toast.error('Failed to search users: ' + error.message);
			searchResults = [];
		} finally {
			isSearching = false;
		}
	}

	function handleSearchEnter() {
		if (searchEmail.trim()) {
			searchUsers();
		} else {
			searchResults = [];
			hasSearched = false;
		}
	}

	function openInviteModal(user: ProjectUserSearchResult) {
		selectedUser = user;
		selectedScopes = ['project.read'];
		showInviteModal = true;
	}

	function closeInviteModal() {
		showInviteModal = false;
		selectedUser = null;
		selectedScopes = ['project.read'];
	}

	async function inviteUser() {
		if (!selectedUser || selectedScopes.length === 0) {
			return;
		}

		try {
			isInviting = true;
			const newProjectUser = await consoleApi.post<ProjectUser>({
				endpoint: 'project-users',
				data: {
					user_id: selectedUser.id,
					scopes: selectedScopes
				}
			});
			
			projectUsers = [...projectUsers, newProjectUser];
			toast.success(`${selectedUser.name} has been invited to the project`);
			closeInviteModal();
			

			searchEmail = '';
			searchResults = [];
		} catch (error: any) {
			toast.error('Failed to invite user: ' + error.message);
		} finally {
			isInviting = false;
		}
	}

	async function deleteUser(projectUser: ProjectUser) {
		// Prevent deletion of current user
		const appConfig = getAppConfig();
		if (appConfig.user && projectUser.user.id === appConfig.user.id) {
			toast.error('You cannot remove yourself from the project');
			return;
		}

		const confirmed = await confirm({
			title: 'Remove User',
			content: `Are you sure you want to remove ${projectUser.user.name} from this project?`,
			confirmText: 'Remove',
			cancelText: 'Cancel',
			danger: true
		});

		if (!confirmed) {
			return;
		}

		try {
			await consoleApi.delete({
				endpoint: `project-users/${projectUser.id}`
			});
			
			projectUsers = projectUsers.filter(pu => pu.id !== projectUser.id);
			toast.success(`${projectUser.user.name} has been removed from the project`);
		} catch (error: any) {
			toast.error('Failed to remove user: ' + error.message);
		}
	}


</script>

<ScopeMask scope="project.write">
	<SettingsBody>
		<div class="user-management">
			<div class="section">
				<h2>Invite Users</h2>
				<p class="section-description">
					Search for users by email address and invite them to collaborate on this project.
				</p>

				<UserSearch
					bind:searchEmail
					{searchResults}
					{isSearching}
					{hasSearched}
					{projectUsers}
					onSearchEnter={handleSearchEnter}
					onInviteUser={openInviteModal}
				/>
			</div>

			<div class="section">
				<h2>Project Users</h2>
				<p class="section-description">
					Users who have access to this project and their permissions.
				</p>

				<UserList
					{projectUsers}
					{isLoadingUsers}
					{availableScopes}
					onDeleteUser={deleteUser}
				/>
			</div>
		</div>
	</SettingsBody>
</ScopeMask>

<InviteUserModal
	bind:show={showInviteModal}
	{selectedUser}
	bind:selectedScopes
	isInviting={isInviting}
	{availableScopes}
	onCancel={closeInviteModal}
	onConfirm={inviteUser}
/>

<style>
	.user-management {
		max-width: 800px;
	}

	.section {
		margin-bottom: 40px;
	}

	.section h2 {
		margin: 0 0 8px 0;
		font-size: 18px;
		font-weight: 600;
	}

	.section-description {
		margin: 0 0 20px 0;
		color: var(--text-light);
		font-size: 14px;
	}
</style>
