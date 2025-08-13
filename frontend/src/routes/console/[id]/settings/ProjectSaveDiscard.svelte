<script lang="ts">
	import { toast } from '@hyvor/design/components';
	import SaveDiscard from '../../@components/content/save/SaveDiscard.svelte';
	import { updateProject } from '../../lib/actions/projectActions';
	import {
		currentProjectEditingctStore,
		setProjectStore
	} from '../../lib/stores/projectStore.svelte';
	import type { Project } from '../../types';

	interface Props {
		onsave?: () => void;
	}

	let { onsave }: Props = $props();

	let hasChanges = $derived($projectEditingStore.name !== $projectStore.name);

	async function onSave() {
		const updatedProject = await updateProject($projectEditingStore.name);

		setProjectStore(updatedProject);

		onsave?.();

		toast.success('Project updated');
	}

	function onDiscard() {
		$projectEditingStore = { ...$projectStore };
	}
</script>

{#if hasChanges}
	<SaveDiscard onsave={onSave} ondiscard={onDiscard} />
{/if}
