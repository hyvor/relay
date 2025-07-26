<script>
	import { Button, SplitControl } from '@hyvor/design/components';
	import { instanceStore } from '../../sudoStore';
</script>

<div class="dns">
	<SplitControl
		label="Custom DNS Records"
		caption="Add custom DNS records for your instance domain {$instanceStore.domain} and its subdomains. Served from the in-built DNS server."
	>
		{#snippet nested()}
			<div class="create">
				<Button>Add Record</Button>
			</div>
		{/snippet}
	</SplitControl>
</div>

<style>
	.dns {
		padding: 30px 40px;
	}
</style>
