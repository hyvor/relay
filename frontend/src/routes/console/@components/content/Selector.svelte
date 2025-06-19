<script lang="ts">
	import { Button, Dropdown, IconButton } from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
    import IconX from '@hyvor/icons/IconX';

	export let name: string;
	export let value: string | undefined = undefined;

	export let show = false;
	export let isSelected = false;
	export let disabled = false;
	export let handleDeselectClick: () => void = () => {};
	export let handleTriggerClick: () => void = () => {};

	export let align: 'start' | 'center' | 'end' = 'start';

	export let width = 400;

</script>

<Dropdown bind:show width={width} align={align}>
	{#snippet trigger()}
	<Button size="small" color="input" on:click={handleTriggerClick} {disabled}>
		<span class="name">
			{name}
		</span>

		<span class="value">
			{#if $$slots.value}
				<slot name="value" />
			{:else}
				{value}
			{/if}
		</span>

		{#if isSelected}
			<IconButton
				size={14}
				style="margin-left:4px;"
				color="gray"
				on:click={(e) => {
					e.stopPropagation();
					handleDeselectClick();
				}}
			>
				<IconX size={10} />
			</IconButton>
		{/if}

		<div class="icon-caret">
			<IconCaretDown size={12} />
		</div>
	</Button>
	{/snippet}

	{#snippet content()}
		<slot />
	{/snippet}
</Dropdown>

<style>
	.value {
		font-weight: normal;
		font-size: 13px;
	}
	.name {
		margin-right: 6px;
	}
	.icon-caret {
		margin-left: 4px;
	}
</style>
