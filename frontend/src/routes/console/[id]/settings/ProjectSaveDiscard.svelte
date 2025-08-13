<script lang="ts">
	import { toast } from '@hyvor/design/components';
	import SaveDiscard from '../../@components/content/save/SaveDiscard.svelte';
	import { updateProject } from '../../lib/actions/projectActions';
	import {
		getCurrentProject,
		getCurrentProjectEditing,
		setCurrentProject,
		setCurrentProjectEditing
	} from '../../lib/stores/projectStore.svelte';

	interface Props {
		onsave?: () => void;
	}

	let { onsave }: Props = $props();

	let project = getCurrentProject();
	let projectEditing = getCurrentProjectEditing();

	let hasChanges = $derived(projectEditing.name !== project.name);

	async function onSave() {
		const updatedProject = await updateProject(projectEditing.name);
		setCurrentProject(updatedProject);
		onsave?.();
		toast.success('Project updated');
	}

	function onDiscard() {
		setCurrentProjectEditing(project);
	}
</script>

{#if hasChanges}
	<SaveDiscard onsave={onSave} ondiscard={onDiscard} />
{/if}
