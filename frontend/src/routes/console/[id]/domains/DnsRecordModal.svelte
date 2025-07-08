<script lang="ts">
	import { Button, Modal, SplitControl } from '@hyvor/design/components';
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
	<div class="modal-content">
		<div class="verify-note">
			Add the following TXT record to your DNS settings, and click the button above to verify your
			domain.
		</div>

		<div class="dns-record">
			<SplitControl
				label="Name"
				caption="DNS record name/host"
			>
				<div class="record-value">
					<div class="value-text">{domain.dkim_txt_name}</div>
					<Button
						size="x-small"
						color="input"
						on:click={() => copyAndToast(domain.dkim_txt_name)}
					>
						COPY
					</Button>
				</div>
			</SplitControl>

			<SplitControl
				label="Value"
				caption="DNS record value/content"
			>
				<div class="record-value">
					<div class="value-text value-long">{domain.dkim_txt_value}</div>
					<Button
						size="x-small"
						color="input"
						on:click={() => copyAndToast(domain.dkim_txt_value)}
					>
						COPY
					</Button>
				</div>
			</SplitControl>
		</div>
	</div>
</Modal>

<style>
	.modal-content {
		padding: 20px 0;
	}

	.verify-note {
		margin-bottom: 24px;
		padding: 16px;
		background-color: var(--bg-light);
		border-radius: 6px;
		font-size: 14px;
		line-height: 1.4;
	}

	.dns-record {
		display: flex;
		flex-direction: column;
		gap: 20px;
	}

	.record-value {
		display: flex;
		align-items: center;
		gap: 12px;
		background-color: var(--bg-light);
		border-radius: 6px;
		padding: 4px;
	}

	.value-text {
		font-family: monospace;
		font-size: 13px;
		padding: 8px;
		flex: 1;
		min-width: 0;
	}

	.value-long {
		max-height: 80px;
		overflow-y: auto;
	}
</style> 