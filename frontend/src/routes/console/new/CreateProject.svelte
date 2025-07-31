<script lang="ts">
	import { goto } from '$app/navigation';
	import {
		Button,
		FormControl,
		Loader,
		Radio,
		SplitControl,
		TextInput,
		Validation,
		toast
	} from '@hyvor/design/components';
	import IconCaretLeft from '@hyvor/icons/IconCaretLeft';
	import { addUserProject, userProjectStore } from '../lib/stores/userProjectStore';
	import { createProject } from '../lib/actions/projectActions';
	import { selectingProject } from '../lib/stores/consoleStore';

	let name = $state('');
	let sendType: 'transactional' | 'distributional' = $state('transactional');

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

		createProject(name, sendType)
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
				<SplitControl label="Name" caption="Simply to identify it later." column>
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

				<SplitControl
					label="Sending Type"
					caption="What type of emails will you send?"
					column
				>
					<div class="type-wrap">
						<FormControl>
							<Radio bind:group={sendType} name="type" value="transactional">
								<div class="td">
									<div class="t">Transactional</div>
									<div class="d">
										These emails are sent to users after they take certain
										actions, like creating an account, resetting a password, or
										confirming a purchase.
									</div>
								</div>
							</Radio>
							<Radio bind:group={sendType} name="type" value="distributional">
								<div class="td">
									<div class="t">Distributional</div>
									<div class="d">
										These emails are sent to many people at once, like
										newsletters, product updates, or marketing campaigns.
									</div>
								</div>
							</Radio>
						</FormControl>
					</div>
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
		width: 650px;
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

	.type-wrap :global(label) {
		height: initial;
	}

	.td .d {
		font-size: 14px;
		color: var(--text-light);
		font-weight: normal;
		margin-top: 3px;
	}
</style>
