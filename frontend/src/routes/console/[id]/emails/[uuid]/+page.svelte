<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import {
		Loader,
		TabNav,
		TabNavItem,
		toast,
		IconMessage,
		Button,
		CodeBlock
	} from '@hyvor/design/components';
	import { getEmail } from '../../../lib/actions/emailActions';
	import type { Email } from '../../../types';
	import EmailStatus from '../EmailStatus.svelte';
	import RelativeTime from '../../../@components/content/RelativeTime.svelte';
	import SingleBox from '../../../@components/content/SingleBox.svelte';
	import InfoItem from '../../../@components/content/InfoItem.svelte';
	import IconCaretLeft from '@hyvor/icons/IconCaretLeft';
	import { consoleUrlProject } from '../../../lib/consoleUrl';
	import { emailStore } from '../../../lib/stores/projectStore';
	import { page } from '$app/state';

	let email: Email | null = $state(null);
	let loading = $state(true);
	let error: string | null = $state(null);
	let activeTab: 'overview' | 'raw' = $state('overview');

	onMount(() => {
		const emailUuid = page.params.uuid;
		const emailId = $emailStore.find((e) => e.uuid === emailUuid)?.id;

		if (!emailId) {
			error = 'Email not found';
			loading = false;
			return;
		}

		getEmail(emailId)
			.then((result) => {
				email = result;
			})
			.catch((err: any) => {
				error = err.message || 'Failed to load email';
				toast.error(error);
			})
			.finally(() => {
				loading = false;
			});
	});

	function formatTimestamp(timestamp: number | undefined): string {
		if (!timestamp) return 'N/A';
		const date = new Date(timestamp * 1000);
		return date.toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
			hour12: true
		});
	}

	function handleBack() {
		goto(consoleUrlProject(`emails`));
	}
</script>

<SingleBox>
	{#if loading}
		<Loader full />
	{:else if error}
		<IconMessage error message={error} />
	{:else if email}
		<div class="email-detail">
			<div class="header">
				<div class="header-left">
					<Button size="small" color="input" as="a" href={consoleUrlProject(`emails`)}>
						{#snippet start()}
							<IconCaretLeft size={12} />
						{/snippet}
						All Emails
					</Button>
				</div>
			</div>

			<div class="tabs">
				<TabNav bind:active={activeTab}>
					<TabNavItem name="overview">Overview</TabNavItem>
					<TabNavItem name="raw">Raw</TabNavItem>
				</TabNav>
			</div>

			{#if activeTab === 'overview'}
				<div class="overview-content">
					<div class="info-grid">
						<InfoItem label="From" value={email.from_address} />

						<InfoItem label="To" value={email.to_address} />

						<InfoItem label="Subject" value={email.subject || 'No subject'} />

						<InfoItem label="Status">
							<EmailStatus status={email.status} />
						</InfoItem>

						<InfoItem label="Created">
							<div>{formatTimestamp(email.created_at)}</div>
							<div class="relative-time">(<RelativeTime unix={email.created_at} />)</div>
						</InfoItem>

						{#if email.sent_at}
							<InfoItem label="Sent">
								<div>{formatTimestamp(email.sent_at)}</div>
								<div class="relative-time">(<RelativeTime unix={email.sent_at} />)</div>
							</InfoItem>
						{/if}

						{#if email.failed_at}
							<InfoItem label="Failed">
								<div>{formatTimestamp(email.failed_at)}</div>
								<div class="relative-time">(<RelativeTime unix={email.failed_at} />)</div>
							</InfoItem>
						{/if}

						<InfoItem label="UUID">
							<div class="uuid">{email.uuid}</div>
						</InfoItem>
					</div>
				</div>
			{/if}

			{#if activeTab === 'raw'}
				<div class="raw-content">
					<div class="raw-content-note">
						This is the raw email content, including headers and body.
					</div>
					<CodeBlock code={email.raw} language={null} />
				</div>
			{/if}
		</div>
	{/if}
</SingleBox>

<style>
	.email-detail {
		height: 100%;
		display: flex;
		flex-direction: column;
		padding: 25px;
	}

	.header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 20px;
		padding-bottom: 15px;
		border-bottom: 1px solid var(--border);
	}

	.header-left {
		display: flex;
		align-items: center;
		gap: 15px;
	}

	.tabs {
		margin-bottom: 20px;
	}

	.overview-content,
	.raw-content {
		flex: 1;
		overflow: auto;
	}

	.info-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 20px;
		margin-bottom: 30px;
	}

	.relative-time {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 4px;
	}

	.uuid {
		font-family: monospace;
		font-size: 12px;
		background: var(--background);
		padding: 4px 8px;
		border-radius: 4px;
		border: 1px solid var(--border);
	}

	.raw-content-note {
		margin-bottom: 10px;
		font-size: 14px;
		color: var(--text-light);
	}

	@media (max-width: 768px) {
		.email-detail {
			padding: 15px;
		}

		.header {
			flex-direction: column;
			align-items: flex-start;
			gap: 15px;
		}

		.header-left {
			flex-direction: column;
			align-items: flex-start;
			gap: 10px;
		}

		.info-grid {
			grid-template-columns: 1fr;
			gap: 15px;
		}
	}
</style>
