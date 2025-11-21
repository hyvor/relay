<script lang="ts">
	import { Caption, SplitControl, Tag, Textarea, TextInput } from '@hyvor/design/components';
	import { instanceStore } from '../sudoStore';
	import InstanceDomain from './instance/InstanceDomain.svelte';
	import IconCheck from '@hyvor/icons/IconCheck';
</script>

<div class="settings">
	<InstanceDomain />
	<SplitControl
		label="Instance DKIM"
		caption="All emails are signed with the instance DKIM keys in addition to the DKIM keys of
				the FROM domain."
	>
		<Tag color="green">
			{#snippet start()}
				<IconCheck />
			{/snippet}
			Enabled
		</Tag>
	</SplitControl>

	<SplitControl
		label="TLS Certificates"
		caption="Automatically generated and renewed using Let's Encrypt."
	>
		{#snippet nested()}
			<SplitControl
				label="Mail Server"
				caption="Used in the incoming mail server (STARTTLS)."
			>
				<Tag color="green">
					{#snippet start()}
						<IconCheck />
					{/snippet}
					Enabled
				</Tag>
			</SplitControl>
		{/snippet}
	</SplitControl>
</div>

<style>
	.settings {
		padding: 30px 50px;
	}
</style>
