<script lang="ts">
	import { Modal, SplitControl, Textarea, toast } from '@hyvor/design/components';
	import { approveKyc, rejectKyc } from '../sudoActions';
	import type { SudoKyc } from '../sudoTypes';

	interface Props {
		kyc: SudoKyc;
		action: 'approve' | 'reject';
		orgName: string;
		show: boolean;
		onDone: (kyc: SudoKyc) => void;
	}

	let { kyc, action, orgName, show = $bindable(false), onDone }: Props = $props();

	let note = $state('');
	let rejectReason = $state('');
	let loading = $state(false);

	function handleConfirm() {
		loading = true;

		const request =
			action === 'approve'
				? approveKyc(kyc.id, { note: note.trim() || null })
				: rejectKyc(kyc.id, {
						note: note.trim() || null,
						reject_reason: rejectReason.trim() || null
					});

		request
			.then((res) => {
				onDone(res);
				toast.success(action === 'approve' ? 'KYC approved.' : 'KYC rejected.');
				show = false;
			})
			.catch((err) => {
				toast.error(`Failed to ${action} KYC: ` + err.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	function handleCancel() {
		show = false;
	}

	$effect(() => {
		if (!show) {
			note = '';
			rejectReason = '';
		}
	});
</script>

<Modal
	bind:show
	title={action === 'approve' ? 'Approve KYC' : 'Reject KYC'}
	footer={{
		confirm: {
			text: action === 'approve' ? 'Approve & Charge' : 'Reject',
			danger: action === 'reject'
		}
	}}
	{loading}
	on:confirm={handleConfirm}
	on:cancel={handleCancel}
>
	<p class="message">
		{#if action === 'approve'}
			Approve KYC for {orgName}? Their card will be charged for the Starter plan right away.
		{:else}
			Reject KYC for {orgName}?
		{/if}
	</p>

	<SplitControl label="Private note" caption="Only visible in sudo.">
		<Textarea bind:value={note} rows={3} block placeholder="Private note" />
	</SplitControl>

	{#if action === 'reject'}
		<SplitControl label="Rejection reason" caption="Reason for rejecting the KYC.">
			<Textarea
				bind:value={rejectReason}
				rows={3}
				block
				placeholder="Rejection reason"
			/>
		</SplitControl>
	{/if}
</Modal>

<style>
	.message {
		margin: 0 0 15px;
	}
</style>
