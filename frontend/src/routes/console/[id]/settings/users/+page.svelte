<script lang="ts">
	import { toast, confirm } from '@hyvor/design/components';
	import SettingsBody from '../../../@components/content/SettingsBody.svelte';
	import ScopeMask from '../../../@components/Scope/ScopeMask.svelte';
	import AddUserModal from './AddUserModal.svelte';
	import UserList from './UserList.svelte';
	import consoleApi from '../../../lib/consoleApi.svelte';
	import { getAppConfig } from '../../../lib/stores/consoleStore';
	import { onMount } from 'svelte';
	import type { ProjectUser, Scope } from '../../../types';
	import { OrganizationMemberSearch } from '@hyvor/design/cloud';

	let projectUsers = $state<ProjectUser[]>([]);
	let isLoadingUsers = $state(true);

	let showInviteModal = $state(false);
	let selectedScopes = $state<string[]>(['project.read']);
	let isAdding = $state(false);
	let addingUserId: number | undefined = $state(undefined);

	$effect(() => {
		if (addingUserId !== undefined) {
			openAddUserModal();
		}
	})

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

	function openAddUserModal() {
		selectedScopes = ['project.read'];
		showInviteModal = true;
	}

	function closeAddUserModal() {
		showInviteModal = false;
		selectedScopes = ['project.read'];
	}

	async function addUser() {
		if (selectedScopes.length === 0) {
			return;
		}

		try {
			isAdding = true;
			const newProjectUser = await consoleApi.post<ProjectUser>({
				endpoint: 'project-users',
				data: {
					user_id: addingUserId,
					scopes: selectedScopes
				}
			});

			projectUsers = [...projectUsers, newProjectUser];
			toast.success(`${newProjectUser.user.name} has been invited to the project`);
			closeAddUserModal();
		} catch (error: any) {
			toast.error('Failed to invite user: ' + error.message);
		} finally {
			isAdding = false;
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
				<h2>Add Users</h2>
				<p class="section-description">
					Search for users and add them to collaborate on this project.
				</p>
				<OrganizationMemberSearch bind:selectedUserId={addingUserId} />
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

<AddUserModal
	bind:show={showInviteModal}
	bind:selectedScopes
	isAdding={isAdding}
	isEditing={false}
	{availableScopes}
	onCancel={closeAddUserModal}
	onConfirm={addUser}
/>

<style>
	.user-management {
        padding: 20px 30px;
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
