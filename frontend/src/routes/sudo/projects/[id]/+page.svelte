<script lang="ts">
	import { page } from '$app/state';
	import { Button, CodeBlock, IconMessage, Loader, Tag } from '@hyvor/design/components';
	import IconCaretLeft from '@hyvor/icons/IconCaretLeft';
	import dayjs from 'dayjs';
	import { onMount } from 'svelte';
	import SingleBox from '../../SingleBox.svelte';
	import { getProjectById } from '../../sudoActions';
	import { flagByCountryCode } from '$lib/helpers/countryCode';
	import type { Organization, SudoProject } from '../../sudoTypes';

	let project: SudoProject | null = $state(null);
	let org: Organization | null = $state(null);
	let loading = $state(true);
	let error: string | null = $state(null);

	onMount(() => {
		getProjectById(Number(page.params.id))
			.then((result) => {
				project = result.project;
				org = result.org;
			})
			.catch((err) => {
				error = err?.message ?? 'Failed to load project';
			})
			.finally(() => {
				loading = false;
			});
	});

	function formatDate(unix: number): string {
		return dayjs.unix(unix).format('YYYY-MM-DD HH:mm:ss');
	}
</script>

<SingleBox>
	{#if loading}
		<Loader full />
	{:else if error}
		<IconMessage error message={error} />
	{:else if project}
		<div class="header">
			<Button size="small" color="input" as="a" href="/sudo/projects">
				{#snippet start()}
					<IconCaretLeft size={12} />
				{/snippet}
				All Projects
			</Button>
			<div class="title">
				{project.name}
				<span class="muted">#{project.id}</span>
			</div>
		</div>

		<div class="content">
			<div class="fields">
				<div class="field">
					<div class="label">Name</div>
					<div class="value">{project.name}</div>
				</div>
				<div class="field">
					<div class="label">ID</div>
					<div class="value">{project.id}</div>
				</div>
				<div class="field">
					<div class="label">Organization</div>
					<div class="value">
						{#if org}
							<div class="org-name">
								{org.name}
								{#if org.billing_address?.country}
									<span title={org.billing_address.country}>
										{flagByCountryCode(org.billing_address.country)}
									</span>
								{/if}
								<span class="muted">#{org.id}</span>
							</div>
							{#if org.billing_email}
								<div class="muted">{org.billing_email}</div>
							{/if}
							<div class="org-link">
								<Button
									as="a"
									href="https://hyvor.com/sudo/core/organizations/{org.id}"
									size="x-small"
									target="_blank"
									color="input"
								>
									Org &rarr;
								</Button>
							</div>
						{:else if project.organization_id !== null}
							#{project.organization_id}
						{:else}
							—
						{/if}
					</div>
				</div>
				<div class="field">
					<div class="label">Send Type</div>
					<div class="value"><Tag size="small">{project.send_type}</Tag></div>
				</div>
				<div class="field">
					<div class="label">Owner (User ID)</div>
					<div class="value">{project.user_id}</div>
				</div>
				<div class="field">
					<div class="label">Created At</div>
					<div class="value">{formatDate(project.created_at)}</div>
				</div>
				<div class="field">
					<div class="label">Updated At</div>
					<div class="value">{formatDate(project.updated_at)}</div>
				</div>
			</div>

			<div class="dump">
				<div class="dump-title">Object dump</div>
				<CodeBlock code={JSON.stringify({ project, org }, null, 4)} language="json" />
			</div>
		</div>
	{/if}
</SingleBox>

<style>
	.header {
		display: flex;
		align-items: center;
		gap: 15px;
		padding: 15px 25px;
		border-bottom: 1px solid var(--border);
	}
	.title {
		font-size: 14px;
		font-weight: 600;
	}
	.muted {
		color: var(--text-light);
		font-weight: normal;
		margin-left: 4px;
	}

	.content {
		flex: 1;
		overflow: auto;
		padding: 25px;
	}

	.fields {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
		gap: 20px;
		margin-bottom: 30px;
	}
	.field .label {
		font-size: 12px;
		color: var(--text-light);
		margin-bottom: 4px;
	}
	.field .value {
		font-size: 14px;
		word-break: break-word;
	}
	.org-name {
		font-weight: 600;
	}
	.org-link {
		margin-top: 4px;
	}

	.dump-title {
		font-size: 14px;
		font-weight: 600;
		margin-bottom: 10px;
	}
</style>
