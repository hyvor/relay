<script lang="ts">
	import { Caption, SplitControl, TextInput } from '@hyvor/design/components';
	import { instanceStore } from '../../sudoStore';

	let instanceDomain = $state($instanceStore.domain);
</script>

<SplitControl label="Instance Domain">
	{#snippet caption()}
		<Caption>
			<a
				href="/hosting/setup#instance-domain"
				style="text-decoration: underline;"
				target="_blank">Instance domain</a
			> is used for sending and receiving emails. Its DNS records are managed by the in-built DNS
			server.
		</Caption>
	{/snippet}
	<TextInput placeholder="Enter the instance domain" value={instanceDomain} block disabled />
</SplitControl>
