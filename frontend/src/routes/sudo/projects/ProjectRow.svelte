<script lang="ts">
	import dayjs from 'dayjs';
	import { Button, TableRow, Tag, Tooltip, toast } from '@hyvor/design/components';
	import { undeleteProject } from '../sudoActions';
	import { type Project, SOFT_DELETE_TTL_DAYS } from '../sudoTypes';

	interface Props {
		project: Project;
		onUndeleted: (id: number) => void;
	}

	let { project, onUndeleted }: Props = $props();
	let restoring = $state(false);

	const deletedAt = $derived(project.deleted_at!);
	const hardDeleteAt = $derived(dayjs.unix(deletedAt).add(SOFT_DELETE_TTL_DAYS, 'day'));
	const daysRemaining = $derived(hardDeleteAt.diff(dayjs(), 'day'));

	async function handleUndelete() {
		if (restoring) return;
		restoring = true;
		try {
			await undeleteProject(project.id);
			toast.success(`Restored "${project.name}"`);
			onUndeleted(project.id);
		} catch (error: any) {
			toast.error('Failed to restore project: ' + error.message);
		} finally {
			restoring = false;
		}
	}
</script>

<TableRow>
	<div class="id">{project.id}</div>
	<div class="name">{project.name}</div>
	<div>{project.organization_id ?? '—'}</div>
	<div>
		<Tooltip text={dayjs.unix(deletedAt).format('YYYY-MM-DD HH:mm:ss')}>
			{dayjs.unix(deletedAt).fromNow()}
		</Tooltip>
	</div>
	<div>
		<Tooltip text={hardDeleteAt.format('YYYY-MM-DD HH:mm:ss')}>
			{#if daysRemaining <= 7}
				<Tag color="red" size="small">{hardDeleteAt.fromNow()}</Tag>
			{:else}
				<Tag size="small">{hardDeleteAt.fromNow()}</Tag>
			{/if}
		</Tooltip>
	</div>
	<div class="actions">
		<Button size="small" color="accent" disabled={restoring} on:click={handleUndelete}>
			{restoring ? 'Restoring…' : 'Undelete'}
		</Button>
	</div>
</TableRow>

<style>
	.id {
		color: var(--text-light);
	}
	.name {
		font-weight: 600;
		word-break: break-all;
	}
	.actions {
		text-align: right;
	}
</style>
