<script lang="ts">
	import { Button, ButtonGroup, Loader, toast } from '@hyvor/design/components';
	import IconCheck from '@hyvor/icons/IconCheck';

	import { getContext, onMount } from 'svelte';
	import { slide } from 'svelte/transition';
	import { saveDiscardBoxClassContextName } from './save';

	interface Props {
		onsave: () => Promise<void>;
		ondiscard: () => void;
	}

	let { onsave, ondiscard }: Props = $props();

	let wrap: HTMLDivElement | undefined = $state();

	let loading = $state(false);
	let success = $state(false);

	const boxClass = getContext(saveDiscardBoxClassContextName) as string | undefined;

	function findBox() {
		if (!wrap) return null;

		const single = wrap.closest('.' + boxClass || '.hds-box');

		if (single) {
			return single;
		}

		return null;
	}

	onMount(() => {
		const closest = findBox();
		if (!closest) return;
		if (!wrap) return;

		const clientRect = closest.getBoundingClientRect();
		wrap.style.left = clientRect.left + 'px';

		if (boxClass) {
			wrap.style.width = clientRect.width + 'px';
		}
	});

	async function save() {
		loading = true;

		try {
			await onsave();
			success = true;
		} catch (e: any) {
			success = false;
			toast.error('Failed to save changes: ' + e.message);
		}

		loading = false;
	}
</script>

<div
	class="wrap"
	bind:this={wrap}
	in:slide
	out:slide={{ delay: success ? 300 : 0, duration: success ? 300 : 0 }}
>
	<div class="save">
		<div class="note">Make sure you save your changes</div>

		<ButtonGroup>
			<Button color="gray" variant="invisible" on:click={ondiscard}>Discard</Button>
			<Button on:click={save}>Save</Button>
		</ButtonGroup>

		{#if loading}
			<div class="loader">
				<Loader size="small" colorTrack="transparent">Saving...</Loader>
			</div>
		{/if}

		{#if success}
			<div class="success">
				<IconCheck size={24} />
			</div>
		{/if}
	</div>
</div>

<style>
	.wrap {
		position: fixed;
		bottom: 0;
		left: 50%;
		transform: translateX(-50%);
		z-index: 100;
		padding: 25px 15px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.loader {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		background-color: var(--accent-lightest);
		font-size: 14px;
		z-index: 1;
	}

	.success {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		z-index: 2;
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--green);
		background-color: var(--accent-lightest);
	}

	.save {
		padding: 10px 30px;
		text-align: right;
		background-color: var(--accent-lightest);
		border-radius: var(--box-radius);
		box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
		display: flex;
		gap: 10px;
		align-items: center;
		position: relative;
		overflow: hidden;
	}
	.note {
		display: inline-flex;
		font-size: 14px;
		border-right: 1px solid var(--accent);
		height: 100%;
		padding-right: 15px;
	}
</style>
