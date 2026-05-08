<script lang="ts">
	import { Button, Divider, SplitControl, TextInput, confirm, toast } from '@hyvor/design/components';
	import { goto } from '$app/navigation';
	import SettingsBody from '../../@components/content/SettingsBody.svelte';
	import ProjectSaveDiscard from './ProjectSaveDiscard.svelte';
	import {
		getCurrentProject,
		getCurrentProjectEditing,
		removeProjectUser
	} from '../../lib/stores/projectStore.svelte';
	import ScopeMask from '../../@components/Scope/ScopeMask.svelte';
	import { deleteProject } from '../../lib/actions/projectActions';
	import { consoleUrl } from '../../lib/consoleUrl';

	let projectEditing = getCurrentProjectEditing();
	let project = getCurrentProject();

	async function handleDeleteProject() {
		const confirmed = await confirm({
			title: 'Delete Project',
			content: `Are you sure you want to delete "${project.name}"? All data will be permanently removed after 30 days.`,
			confirmText: 'Delete',
			cancelText: 'Cancel',
			danger: true,
			autoClose: false
		});

		if (!confirmed) return;

		confirmed.loading();

		try {
			await deleteProject();
			removeProjectUser(project.id);
			toast.success(`"${project.name}" has been deleted`);
			goto(consoleUrl(''));
		} catch (error: any) {
			toast.error('Failed to delete project: ' + error.message);
		} finally {
			confirmed.close();
		}
	}
</script>

<ScopeMask scope="project.write">
	<SettingsBody>
		<SplitControl label="Project">
			<TextInput block bind:value={projectEditing.name} />
		</SplitControl>

		<SplitControl
			label="Delete Project"
			caption="Deletes this project. All data will be permanently removed after 30 days."
		>
			<div class="danger-action">
				<Button color="red" on:click={handleDeleteProject}>Delete Project</Button>
			</div>
		</SplitControl>

	</SettingsBody>
</ScopeMask>

<div class="save-discard-wrapper">
	<ProjectSaveDiscard />
</div>

<style>
	.save-discard-wrapper {
		display: flex;
		justify-content: center;
		width: 100%;
		margin-top: 20px;
	}

	.danger-action {
		display: flex;
		justify-content: flex-end;
	}
</style>
