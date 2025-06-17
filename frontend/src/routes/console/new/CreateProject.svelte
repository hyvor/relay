<script lang="ts">
	import { goto } from '$app/navigation';
	import {
		Button,
		FormControl,
		Loader,
		SplitControl,
		TextInput,
		Validation,
		toast
	} from '@hyvor/design/components';
	import IconCaretLeft from '@hyvor/icons/IconCaretLeft';
	import { addUserProject, userProjectStore } from '../lib/stores/userProjectStore';
	import { createProject } from '../lib/actions/projectActions';

	let name = $state('');

	let nameError: string | null = $state(null);

	let isCreating = $state(false);

	function handleBack() {
		if ($userProjectStore.length > 0) {
			goto('/console');
		} else {
			goto('/');
		}
	}

	function handleNameInput(e: any) {
		nameError = null;

		const value = e.target.value;
	}

	function handleCreate() {
		let valid = true;
		if (name.trim() === '') {
			nameError = 'Name is required';
			valid = false;
		}

		if (!valid) {
			return;
		}

		isCreating = true;

		createProject(name)
			.then((res) => {
				toast.success('Project created successfully');

				addUserProject(res);

				goto('/console/' + res.id);
			})
			.catch((e) => {
				toast.error(e.message);
			})
			.finally(() => {
				isCreating = false;
			});
	}
</script>

<div class="wrap">
	<div class="inner hds-box">
		<div class="back">
			<Button variant="outline" size="small" on:click={handleBack} disabled={isCreating}>
				{#snippet start()}
					<IconCaretLeft size={14} />
				{/snippet}
				Back
			</Button>
		</div>

		{#if isCreating}
			<Loader block padding={130}>Creating your project...</Loader>
		{:else}
			<div class="title">Start a new project</div>

			<div class="form">
				<SplitControl label="Name" caption="A name for your project">
					<FormControl>
						<TextInput
							block
							bind:value={name}
							on:input={handleNameInput}
							on:keydown={(e) => e.key === 'Enter' && handleCreate()}
							maxlength="255"
							state={nameError ? 'error' : undefined}
							autofocus
						/>

						{#if nameError}
							<Validation state="error">
								{nameError}
							</Validation>
						{/if}
					</FormControl>
				</SplitControl>
			</div>

			<div class="footer">
				<Button size="large" on:click={handleCreate}>Create Project</Button>
			</div>
		{/if}
	</div>
</div>

<style>
	.back {
		position: absolute;
		bottom: 100%;
		left: 0;
		padding: 15px 0;
	}
	.wrap {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 100vh;
	}
	.title {
		padding: 25px;
		font-weight: 600;
		font-size: 22px;
		text-align: center;
	}
	.inner {
		width: 550px;
		max-width: 100%;
		position: relative;
	}
	.form {
		padding: 0 20px;
	}
	.footer {
		padding: 20px;
		padding-bottom: 30px;
		text-align: center;
	}
</style>
