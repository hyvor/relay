<script lang="ts">
	import { Button, Modal, SplitControl, Table, TableRow } from '@hyvor/design/components';
	import type { Domain } from '../../types';
	import { copyAndToast } from '../../lib/helpers/copy';

	export let domain: Domain;
	export let show: boolean;
</script>

<Modal
	bind:show
	size="large"
	title="DNS Records"
	footer={{
        cancel: {
            text: 'Close',
        }, 
		confirm: false
    }}
	on:cancel={() => show = false}
>
	<div class="verify-note">
		Add the following TXT record to your DNS settings, and click the button above to verify your
		domain.
	</div>

	<Table columns="1fr 2fr">
		<TableRow head>
			<div>Name</div>
			<div>Value</div>
		</TableRow>
		<TableRow>
			<div>
				{domain.dkim_txt_name} <br />
				<Button
					size="x-small"
					color="input"
					on:click={() => copyAndToast(domain.dkim_txt_name)}>COPY</Button
				>
			</div>
			<div style="word-break:break-all">
				{domain.dkim_txt_value} <br />
				<Button
					size="x-small"
					color="input"
					on:click={() => copyAndToast(domain.dkim_txt_value)}>COPY</Button
				>
			</div>
		</TableRow>
	</Table>
</Modal>

<style>
	.verify-note {
		margin: 15px 0;
	}
</style> 