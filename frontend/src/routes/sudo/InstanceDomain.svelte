<script>
	import { IconButton } from '@hyvor/design/components';
	import IconPencil from '@hyvor/icons/IconPencil';
	import { instanceStore } from './sudoStore';
</script>

<div class="instance-domain">
	<div class="value">{$instanceStore.domain}</div>

	<a href="/sudo/settings?instance-domain">
		<IconButton size={22} color="input">
			<IconPencil size={11} />
		</IconButton>
	</a>
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
