<script lang="ts">
	import { Caption, SplitControl, Textarea, TextInput } from '@hyvor/design/components';
	import SingleBox from '../SingleBox.svelte';
	import { instanceStore } from '../sudoStore';
	import InstanceDomain from './InstanceDomain.svelte';
</script>

<SingleBox>
	<div class="settings">
		<InstanceDomain />
		<SplitControl label="Instance DKIM">
			{#snippet caption()}
				<Caption
					>All emails are signed with the instance DKIM keys in addition to the DKIM keys
					of the FROM domain. <br />Add the following TXT record to your DNS settings. If
					you use DNS automation, this is automatically handled.</Caption
				>
			{/snippet}

			{#snippet nested()}
				<SplitControl label="Host">
					<TextInput value={$instanceStore.dkim_host} block readonly />
				</SplitControl>
				<SplitControl label="TXT Value">
					<Textarea block value={$instanceStore.dkim_txt_value} rows={5} readonly
					></Textarea>
				</SplitControl>
			{/snippet}
		</SplitControl>
	</div>
</SingleBox>

<style>
	.settings {
		padding: 30px 50px;
	}
</style>
