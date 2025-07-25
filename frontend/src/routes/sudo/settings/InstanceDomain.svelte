<script lang="ts">
	import { Button, SplitControl, TextInput, toast } from '@hyvor/design/components';
	import { instanceStore } from '../sudoStore';
	import { updateInstance } from '../sudoActions';
	import { onMount } from 'svelte';

	let instanceDomain = $state($instanceStore.domain);
	let updating = $state(false);
	let input = $state({} as HTMLInputElement);

	function handleClick() {
		if (updating) return;

		if (instanceDomain.trim() === '') {
			toast.error('Instance domain cannot be empty.');
			return;
		}

		updateInstance({
			domain: instanceDomain.trim()
		})
			.finally(() => {
				updating = false;
			})
			.catch((error) => {
				toast.error(error.message);
			});
	}

	onMount(() => {
		if (location.search.includes('instance-domain')) {
			input.focus();
		}
	});
</script>

<SplitControl label="Instance Domain">
	<div class="instance-domain-input">
		<TextInput
			placeholder="Enter the instance domain"
			bind:value={instanceDomain}
			block
			bind:input
			on:keyup={(e) => e.key === 'Enter' && handleClick()}
		/>
		<div class="save">
			<Button
				disabled={instanceDomain === $instanceStore.domain ||
					instanceDomain.trim() === '' ||
					updating}
				onclick={handleClick}
			>
				Save</Button
			>
		</div>
	</div>
</SplitControl>

<style>
	.instance-domain-input {
		display: flex;
		align-items: center;
		gap: 10px;
	}
</style>
