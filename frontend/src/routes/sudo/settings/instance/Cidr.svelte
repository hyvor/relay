<script lang="ts">
	import { Button, SplitControl, TextInput, toast } from '@hyvor/design/components';
	import { instanceStore } from '../../sudoStore';
	import { updateInstance } from '../../sudoActions';

	let cidr = $state($instanceStore.private_network_cidr);
	let updating = $state(false);
	let input = $state({} as HTMLInputElement);

	function handleClick() {
		if (updating) return;

		if (cidr.trim() === '') {
			toast.error('CIDR cannot be empty.');
			return;
		}

		if (cidr.trim() === $instanceStore.private_network_cidr) {
			return;
		}

		updating = true;

		updateInstance({
			private_network_cidr: cidr.trim()
		})
			.then(() => {
				toast.success('CIDR updated successfully.');
				input.blur();
			})
			.finally(() => {
				updating = false;
			})
			.catch((error) => {
				toast.error(error.message);
			});
	}
</script>

<SplitControl
	label="Private Network CIDR"
	caption="IP range for the private network. This is used for internal communication between servers."
>
	<div class="cidr-input">
		<TextInput
			placeholder="Enter the CIDR"
			bind:value={cidr}
			block
			bind:input
			on:keyup={(e) => e.key === 'Enter' && handleClick()}
		/>
		<div class="save">
			<Button
				disabled={cidr.trim() === $instanceStore.private_network_cidr ||
					cidr.trim() === '' ||
					updating}
				onclick={handleClick}
			>
				Save</Button
			>
		</div>
	</div>
</SplitControl>

<style>
	.cidr-input {
		display: flex;
		align-items: center;
		gap: 10px;
	}
</style>
