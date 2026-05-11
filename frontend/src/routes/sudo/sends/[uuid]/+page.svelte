<script lang="ts">
	import {
		Button,
		CodeBlock,
		IconMessage,
		Loader,
		Tag,
		TabNav,
		TabNavItem,
		toast
	} from '@hyvor/design/components';
	import IconArrowLeft from '@hyvor/icons/IconArrowLeft';
	import IconBoxArrowUpRight from '@hyvor/icons/IconBoxArrowUpRight';
	import dayjs from 'dayjs';
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import SingleBox from '../../SingleBox.svelte';
	import { getSendByUuid } from '../../sudoActions';
	import type {
		SendAttemptStatus,
		SudoSendDetail,
		SudoSendRecipientStatus
	} from '../../sudoTypes';

	let send: SudoSendDetail | null = $state(null);
	let loading = $state(true);
	let error: string | null = $state(null);
	let activeTab: 'overview' | 'preview' | 'raw' = $state('overview');

	onMount(() => {
		getSendByUuid(page.params.uuid ?? '')
			.then((res) => {
				send = res;
			})
			.catch((err: { message?: string }) => {
				error = err?.message ?? 'Failed to load send';
				toast.error(error);
			})
			.finally(() => {
				loading = false;
			});
	});

	function recipientStatusMeta(
		status: SudoSendRecipientStatus
	): { color: string; text: string } {
		switch (status) {
			case 'accepted':
				return { color: 'green', text: 'Accepted' };
			case 'bounced':
				return { color: 'red', text: 'Bounced' };
			case 'complained':
				return { color: 'red', text: 'Complained' };
			case 'queued':
				return { color: 'default', text: 'Queued' };
			case 'deferred':
				return { color: 'orange', text: 'Deferred' };
			case 'suppressed':
				return { color: 'red', text: 'Suppressed' };
			case 'failed':
				return { color: 'red', text: 'Failed' };
		}
	}

	function attemptStatusMeta(
		status: SendAttemptStatus
	): { color: string; text: string } {
		switch (status) {
			case 'accepted':
				return { color: 'green', text: 'Accepted' };
			case 'deferred':
				return { color: 'orange', text: 'Deferred' };
			case 'bounced':
				return { color: 'red', text: 'Bounced' };
			case 'partial':
				return { color: 'orange', text: 'Partial' };
			case 'failed':
				return { color: 'red', text: 'Failed' };
		}
	}

	function formatBytes(bytes: number): string {
		if (bytes < 1024) return `${bytes} B`;
		if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
		return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
	}
</script>

<SingleBox>
	{#if loading}
		<Loader full />
	{:else if error}
		<IconMessage error message={error} />
	{:else if send}
		<div class="detail">
			<div class="header">
				<div class="header-left">
					<Button as="a" href="/sudo/sends" size="small" color="input">
						{#snippet start()}
							<IconArrowLeft size={14} />
						{/snippet}
						All Sends
					</Button>
					<div class="title-block">
						<h2>{send.subject ?? '(no subject)'}</h2>
						<div class="sub">
							<a class="project" href="/sudo/sends?project_id={send.project.id}">
								{send.project.name}
								<span class="muted">#{send.project.id}</span>
							</a>
							<span class="dot">·</span>
							<span class="muted">
								{dayjs.unix(send.created_at).format('YYYY-MM-DD HH:mm:ss')}
							</span>
						</div>
					</div>
				</div>
				<Button
					as="a"
					href={`/console/${send.project.id}/sends/${send.uuid}`}
					size="small"
					color="input"
				>
					{#snippet end()}
						<IconBoxArrowUpRight size={12} />
					{/snippet}
					View in Console
				</Button>
			</div>

			<div class="tabs">
				<TabNav>
					<TabNavItem
						name="overview"
						active={activeTab === 'overview'}
						onclick={() => (activeTab = 'overview')}
					>
						Overview
					</TabNavItem>
					<TabNavItem
						name="preview"
						active={activeTab === 'preview'}
						onclick={() => (activeTab = 'preview')}
					>
						Preview
					</TabNavItem>
					<TabNavItem
						name="raw"
						active={activeTab === 'raw'}
						onclick={() => (activeTab = 'raw')}
					>
						Raw
					</TabNavItem>
				</TabNav>
			</div>

			{#if activeTab === 'overview'}
				<div class="tab-content">
					<section>
						<h3>Metadata</h3>
						<div class="meta-grid">
							<div class="meta">
								<div class="meta-label">UUID</div>
								<div class="meta-value mono">{send.uuid}</div>
							</div>
							<div class="meta">
								<div class="meta-label">From</div>
								<div class="meta-value">
									{send.from_name
										? `${send.from_name} <${send.from_address}>`
										: send.from_address}
								</div>
							</div>
							<div class="meta">
								<div class="meta-label">Created</div>
								<div class="meta-value">
									{dayjs.unix(send.created_at).format('YYYY-MM-DD HH:mm:ss')}
								</div>
							</div>
							<div class="meta">
								<div class="meta-label">Send after</div>
								<div class="meta-value">
									{dayjs.unix(send.send_after).format('YYYY-MM-DD HH:mm:ss')}
								</div>
							</div>
							<div class="meta">
								<div class="meta-label">Queued</div>
								<div class="meta-value">
									<Tag size="small" color={send.queued ? 'orange' : 'default'}>
										{send.queued ? 'Yes' : 'No'}
									</Tag>
								</div>
							</div>
							<div class="meta">
								<div class="meta-label">Size</div>
								<div class="meta-value">{formatBytes(send.size_bytes)}</div>
							</div>
						</div>
					</section>

					<section>
						<h3>Recipients ({send.recipients.length})</h3>
						<div class="list">
							{#each send.recipients as recipient (recipient.id)}
								{@const meta = recipientStatusMeta(recipient.status)}
								<div class="list-row">
									<span class="address">{recipient.address}</span>
									<Tag size="small" color={meta.color as any}>
										{meta.text}
									</Tag>
								</div>
							{/each}
						</div>
					</section>

					<section>
						<h3>Attempts ({send.attempts.length})</h3>
						{#if send.attempts.length === 0}
							<div class="empty">No attempts yet.</div>
						{:else}
							<div class="list">
								{#each send.attempts as attempt (attempt.id)}
									{@const meta = attemptStatusMeta(attempt.status)}
									<div class="attempt">
										<div class="attempt-head">
											<div>
												<span class="muted">Try #{attempt.try_count}</span>
												<span class="dot">·</span>
												<span class="muted">{attempt.domain}</span>
												<span class="dot">·</span>
												<span class="muted">{attempt.duration_ms}ms</span>
												<span class="dot">·</span>
												<span class="muted">
													{dayjs.unix(attempt.created_at).fromNow()}
												</span>
											</div>
											<Tag size="small" color={meta.color as any}>
												{meta.text}
											</Tag>
										</div>
										{#if attempt.responded_mx_host}
											<div class="muted small">
												MX: {attempt.responded_mx_host}
											</div>
										{/if}
									</div>
								{/each}
							</div>
						{/if}
					</section>

					<section>
						<h3>Feedback ({send.feedback.length})</h3>
						{#if send.feedback.length === 0}
							<div class="empty">No feedback received.</div>
						{:else}
							<div class="list">
								{#each send.feedback as fb (fb.id)}
									<div class="list-row">
										<span>
											<span class="muted">Recipient #{fb.recipient_id}</span>
											<span class="dot">·</span>
											<span class="muted">
												{dayjs.unix(fb.created_at).format('YYYY-MM-DD HH:mm:ss')}
											</span>
										</span>
										<Tag size="small" color={fb.type === 'complaint' ? 'red' : 'orange'}>
											{fb.type === 'complaint' ? 'Complaint' : 'Bounce'}
										</Tag>
									</div>
								{/each}
							</div>
						{/if}
					</section>
				</div>
			{/if}

			{#if activeTab === 'preview'}
				<div class="tab-content">
					{#if send.body_html}
						<iframe
							class="preview-frame"
							srcdoc={send.body_html}
							sandbox="allow-same-origin"
							title="Email preview"
						></iframe>
					{:else if send.body_text}
						<pre class="text-preview">{send.body_text}</pre>
					{:else}
						<div class="empty">No body content.</div>
					{/if}
				</div>
			{/if}

			{#if activeTab === 'raw'}
				<div class="tab-content">
					<div class="muted small raw-note">
						Raw email content including headers and body.
					</div>
					<CodeBlock code={send.raw} language={null} />
				</div>
			{/if}
		</div>
	{/if}
</SingleBox>

<style>
	.detail {
		padding: 20px 30px;
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 20px;
		overflow: auto;
	}

	.header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 15px;
	}

	.header-left {
		display: flex;
		align-items: center;
		gap: 15px;
		flex: 1;
		min-width: 0;
	}

	.title-block {
		min-width: 0;
	}

	.title-block h2 {
		margin: 0;
		font-size: 20px;
		word-break: break-word;
	}

	.sub {
		margin-top: 4px;
		font-size: 13px;
		display: flex;
		align-items: center;
		gap: 6px;
		flex-wrap: wrap;
	}

	.project {
		color: inherit;
		text-decoration: none;
	}

	.project:hover {
		text-decoration: underline;
	}

	.muted {
		color: var(--text-light);
	}

	.small {
		font-size: 13px;
	}

	.dot {
		color: var(--text-light);
	}

	.tabs {
		border-bottom: 1px solid var(--border);
		margin: 0 -30px;
		padding: 0 30px;
	}

	.tab-content {
		display: flex;
		flex-direction: column;
		gap: 25px;
	}

	section h3 {
		margin: 0 0 12px;
		font-size: 14px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--text-light);
	}

	.meta-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
		gap: 12px;
	}

	.meta {
		padding: 12px 14px;
		background: var(--bg-light);
		border-radius: var(--box-radius);
	}

	.meta-label {
		font-size: 12px;
		color: var(--text-light);
		margin-bottom: 4px;
	}

	.meta-value {
		word-break: break-word;
	}

	.mono {
		font-family: var(--font-mono, monospace);
		font-size: 13px;
	}

	.list {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	.list-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 10px 14px;
		background: var(--bg-light);
		border-radius: var(--box-radius);
	}

	.address {
		word-break: break-all;
	}

	.attempt {
		padding: 10px 14px;
		background: var(--bg-light);
		border-radius: var(--box-radius);
	}

	.attempt-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
	}

	.empty {
		color: var(--text-light);
		font-size: 13px;
		padding: 8px 0;
	}

	.preview-frame {
		width: 100%;
		min-height: 600px;
		border: 1px solid var(--border);
		border-radius: var(--box-radius);
		background: white;
	}

	.text-preview {
		padding: 16px;
		background: var(--bg-light);
		border-radius: var(--box-radius);
		white-space: pre-wrap;
		word-break: break-word;
	}

	.raw-note {
		margin-bottom: 8px;
	}
</style>
