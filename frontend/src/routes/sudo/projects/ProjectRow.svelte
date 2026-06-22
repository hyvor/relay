<script lang="ts">
	import { Button, Tag } from '@hyvor/design/components';
	import RelativeTime from '../../console/@components/content/RelativeTime.svelte';
	import { flagByCountryCode } from '$lib/helpers/countryCode';
	import type { Organization, SudoProject } from '../sudoTypes';

	interface Props {
		project: SudoProject;
		org: Organization | null;
	}

	let { project, org }: Props = $props();
</script>

<a class="row" href="/sudo/projects/{project.id}">
	<div class="muted">#{project.id}</div>
	<div class="name">
		{project.name}
		<div class="created"><RelativeTime unix={project.created_at} /></div>
	</div>
	<div class="org">
		{#if org}
			<div class="org-name">
				{org.name}
				{#if org.billing_address?.country}
					<span title={org.billing_address.country}>
						{flagByCountryCode(org.billing_address.country)}
					</span>
				{/if}
			</div>
			{#if org.billing_email}
				<div class="org-email">{org.billing_email}</div>
			{/if}
			<div class="view-button">
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
			<span class="muted">org {project.organization_id}</span>
		{:else}
			<span class="muted">—</span>
		{/if}
	</div>
	<div>
		<Tag size="x-small">{project.send_type}</Tag>
	</div>
</a>

<style>
	.row {
		display: grid;
		grid-template-columns: 0.5fr 3fr 2fr 1.5fr;
		gap: 15px;
		align-items: start;
		padding: 15px 30px;
		text-align: left;
		width: 100%;
		word-break: break-word;
		font-size: 14px;
	}
	.row:hover {
		background: var(--hover);
	}
	.name {
		font-weight: 600;
	}
	.created {
		font-weight: 400;
		color: var(--text-light);
		font-size: 13px;
		margin-top: 2px;
	}
	.muted {
		color: var(--text-light);
		font-size: 14px;
	}
	.org {
		font-size: 13px;
	}
	.org-name {
		font-weight: 600;
	}
	.org-email {
		font-size: 13px;
		color: var(--text-light);
	}
	.view-button {
		margin-top: 3px;
	}
</style>
