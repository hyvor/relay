<script lang="ts">
	import dayjs from 'dayjs';
	import type { DebugIncomingEmail } from '../../sudoTypes';
	import { Button, CodeBlock, Tag, toast } from '@hyvor/design/components';
	import IconCaretDown from '@hyvor/icons/IconCaretDown';
	import IconCaretUp from '@hyvor/icons/IconCaretUp';

	interface Props {
		mail: DebugIncomingEmail;
	}

	let { mail }: Props = $props();
	let opened = $state(false);
</script>

<div class="wrap">
	<button class="row" onclick={() => (opened = !opened)} class:opened>
		<div class="date">
			{dayjs.unix(mail.created_at).format('YYYY-MM-DD HH:mm:ss')}
		</div>
		<div class="type">
			<Tag>{mail.type.toUpperCase()}</Tag>
		</div>
		<div class="status">
			{#if mail.status === 'success'}
				<Tag color="green">Success</Tag>
			{:else}
				<Tag color="red">Failed</Tag>
				<div class="error">
					{mail.error_message || 'No error message provided.'}
				</div>
			{/if}
		</div>
		<div class="envelope">
			<div class="from">
				From: {mail.mail_from}
			</div>
			<div class="to">
				To: {mail.rcpt_to}
			</div>
		</div>
		<div class="caret">
			{#if opened}
				<IconCaretUp />
			{:else}
				<IconCaretDown />
			{/if}
		</div>
	</button>

	{#if opened}
		<div class="details">
			<div class="raw">
				<div class="title">Raw Email</div>
				<CodeBlock code={mail.raw_email} language={null} />
				<div class="copy">
					<Button
						size="small"
						onclick={() => {
							navigator.clipboard.writeText(mail.raw_email);
							toast.success('Raw email copied to clipboard.');
						}}
					>
						Copy Raw Email
					</Button>
				</div>
			</div>

			{#if mail.parsed_data}
				<div class="parsed">
					<div class="title">Parsed Data</div>
					<CodeBlock code={JSON.stringify(mail.parsed_data, null, 2)} language="json" />
				</div>
			{/if}
		</div>
	{/if}
</div>

<style>
	.row {
		width: 100%;
		display: grid;
		align-items: center;
		grid-template-columns: 1fr 1fr 2fr 2fr 50px;
		gap: 10px;
		padding: 15px 25px;
		border-radius: 20px;
		cursor: pointer;
		text-align: left;
	}
	.row.opened {
		background-color: var(--hover);
	}
	.row:hover {
		background-color: var(--hover);
	}

	.error {
		color: var(--text-light);
		font-size: 14px;
		margin-top: 3px;
	}

	.caret {
		text-align: right;
	}
	.details {
		margin-left: 25px;
		padding: 15px;
		border-left: 2px solid var(--border);
	}
	.title {
		font-weight: 600;
	}

	.details :global(pre) {
		max-height: 400px;
		overflow-y: auto;
	}

	.copy {
		margin-top: 10px;
	}

	.parsed {
		margin-top: 10px;
		padding-top: 10px;
		border-top: 1px solid var(--border);
	}
</style>
