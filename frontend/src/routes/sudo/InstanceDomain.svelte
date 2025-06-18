<script>
	import { IconButton } from '@hyvor/design/components';
	import IconPencil from '@hyvor/icons/IconPencil';
</script>

<div class="instance-domain">
	<div class="value">relay.localhost</div>
	<IconButton size={22} color="input">
		<IconPencil size={11} />
	</IconButton>
</div>

<style>
	.instance-domain {
		padding: 10px 30px 25px;
		display: flex;
		align-items: center;
		gap: 6px;
	}
	.value {
		font-weight: 600;
		flex: 1;
		word-wrap: break-word;
		min-width: 0;
	}
</style>
