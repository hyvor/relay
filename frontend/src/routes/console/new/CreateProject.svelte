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
	import { createProject } from '../lib/actions/projectActions';
	import {
		addProjectUser,
		getProjectUsers,
		setCurrentProjectUser
	} from '../lib/stores/projectStore.svelte';
	import { ResourceCreator, type CloudContextOrganization, getCloudContext } from '@hyvor/design/cloud';

	let name = $state('');
	let sendType: 'transactional' | 'distributional' = $state('transactional');

	let nameError: string | null = $state(null);

	let isCreating = $state(false);
	let cloudContext = getCloudContext();
	let projectUsers = getProjectUsers();

	function handleBack() {
		if (projectUsers.length > 0) {
			goto('/console');
		} else {
			goto('/');
		}
	}

	function handleNameInput(e: any) {
		nameError = null;

		const value = e.target.value;
	}

	async function handleCreate(organization: CloudContextOrganization) {
		let valid = true;
		if (name.trim() === '') {
			nameError = 'Name is required';
			valid = false;
		}

		if (!valid) {
			return false;
		}

		isCreating = true;

		createProject(name, sendType)
			.then((res) => {
				toast.success('Project created successfully');
				addProjectUser(res);
				setCurrentProjectUser(res);
				goto('/console/' + res.project.id);
			})
			.catch((e) => {
				toast.error(e.message);
			})
			.finally(() => {
				isCreating = false;
			});

		return true; // TODO
	}
</script>

<ResourceCreator
	title="Start a new project"
	resourceTitle="Project"
	cta="Create Project"
	onback={handleBack}
	oncreate={handleCreate}
	ctaDisabled={name.trim() === ''}
>
	<SplitControl label="Name" caption="Simply to identify it later." column>
		<FormControl>
			<TextInput
				block
				bind:value={name}
				on:input={handleNameInput}
				on:keydown={(e) => e.key === 'Enter' && handleCreate(cloudContext.organization!)}
				maxlength={255}
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

</ResourceCreator>

<style>
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
