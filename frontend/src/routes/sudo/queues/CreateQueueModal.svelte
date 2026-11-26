<script lang="ts">
	import {
		FormControl,
		Modal,
		Radio,
		SplitControl,
		TextInput,
		toast
	} from '@hyvor/design/components';
	import type { QueueType } from '../sudoTypes';
	import { createQueue } from '../sudoActions';
	import { queuesStore } from '../sudoStore';

	interface Props {
		show: boolean;
	}

	let { show = $bindable(false) }: Props = $props();

	let type: QueueType = $state('transactional');
	let name = $state('');

	let loading = $state(false);

	function handleConfirm() {
		const trimmedName = name.trim();

		if (trimmedName === '') {
			toast.error('Name cannot be empty');
			return;
		}

		loading = true;

		createQueue({ type, name: trimmedName })
			.then((queue) => {
				toast.success(`Queue ${queue.full_name} created successfully`);
				queuesStore.update((queues) => [...queues, queue]);
				show = false;
			})
			.catch((error) => {
				toast.error('Failed to create queue: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	}
</script>

<Modal
	bind:show
	title="Create Queue"
	footer={{ confirm: { text: 'Create' } }}
	on:confirm={handleConfirm}
	{loading}
>
	<SplitControl label="Type">
		<FormControl>
			<Radio value="transactional" bind:group={type}>Transactional</Radio>
			<Radio value="distributional" bind:group={type}>Distributional</Radio>
		</FormControl>
	</SplitControl>
	<SplitControl
		label="Name"
		caption="Lowercase letters, numbers, hyphens and underscores only. Must be unique within the type."
	>
		<TextInput bind:value={name} block maxlength={64} />
	</SplitControl>
</Modal>
