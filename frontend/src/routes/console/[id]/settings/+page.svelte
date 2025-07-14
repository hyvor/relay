<script lang="ts">
	import { TextInput, SplitControl, Button, toast, confirm } from '@hyvor/design/components';
	import { goto } from '$app/navigation';
	import { get } from 'svelte/store';
	import SettingsBody from '../../@components/content/SettingsBody.svelte';
	import ProjectSaveDiscard from './ProjectSaveDiscard.svelte';
	import { projectEditingStore } from '../../lib/stores/projectStore';

	let deleting = false;

	async function onDelete() {
	}

</script>

<SettingsBody>
	<SplitControl label="Project">
		<TextInput block bind:value={$projectEditingStore.name} />
	</SplitControl>
</SettingsBody>

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
