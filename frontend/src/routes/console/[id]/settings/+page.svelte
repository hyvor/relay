<script lang="ts">
	import { TextInput, SplitControl } from '@hyvor/design/components';
	import SettingsBody from '../../@components/content/SettingsBody.svelte';
	import ProjectSaveDiscard from './ProjectSaveDiscard.svelte';
	import { getCurrentProjectEditing } from '../../lib/stores/projectStore.svelte';
	import ScopeMask from '../../@components/Scope/ScopeMask.svelte';

	let projectEditing = getCurrentProjectEditing();
</script>

<ScopeMask scope="project.write">
	<SettingsBody>
		<SplitControl label="Project">
			<TextInput block bind:value={projectEditing.name} />
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
</style>
