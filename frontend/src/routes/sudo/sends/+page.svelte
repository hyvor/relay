<script lang="ts">
	import SingleBox from '../SingleBox.svelte';
	import Filters from '$lib/sends/Filters.svelte';
	import SendsList from '$lib/sends/SendsList.svelte';
	import ProjectSelectDropdown from './ProjectSelectDropdown.svelte';
	import { getSends } from '../sudoActions';
	import type { Send, SendRecipientStatus, StatusOption } from '$lib/sends/types';
	import type { SudoProject } from '../sudoTypes';

	const STATUS_OPTIONS: StatusOption[] = [
		{ value: null, label: 'All' },
		{ value: 'queued', label: 'Queued' },
		{ value: 'accepted', label: 'Accepted' },
		{ value: 'deferred', label: 'Deferred' },
		{ value: 'bounced', label: 'Bounced' },
		{ value: 'complained', label: 'Complained' },
		{ value: 'suppressed', label: 'Suppressed' },
		{ value: 'failed', label: 'Failed' }
	];

	const PER_PAGE = 25;

	let selectedProject = $state<SudoProject | null>(null);
	let status: SendRecipientStatus | null = $state(null);
	let fromSearch: string = $state('');
	let toSearch: string = $state('');
	let subjectSearch: string = $state('');
	let dateFromSearch: string | null = $state(null);
	let dateToSearch: string | null = $state(null);

	let queryKey = $derived(
		[
			selectedProject?.id ?? '',
			status,
			fromSearch,
			toSearch,
			subjectSearch,
			dateFromSearch,
			dateToSearch
		].join('|')
	);

	function fetchSends(beforeId: number | null): Promise<Send[]> {
		return getSends({
			project_id: selectedProject?.id ?? null,
			status,
			from_search: fromSearch || null,
			to_search: toSearch || null,
			subject_search: subjectSearch || null,
			date_from_search: dateFromSearch,
			date_to_search: dateToSearch,
			limit: PER_PAGE,
			before_id: beforeId
		});
	}
</script>

<SingleBox>
	<div class="top">
		<div class="filters">
			<ProjectSelectDropdown bind:value={selectedProject} />
			<Filters
				statusOptions={STATUS_OPTIONS}
				bind:status
				bind:fromSearch
				bind:toSearch
				bind:subjectSearch
				bind:dateFromSearch
				bind:dateToSearch
			/>
		</div>
	</div>

	<SendsList
		{fetchSends}
		hrefBuilder={(s) => `/sudo/sends/${s.uuid}`}
		showProject
		{queryKey}
		perPage={PER_PAGE}
	/>
</SingleBox>

<style>
	.top {
		display: flex;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}
	.filters {
		flex: 1;
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		align-items: center;
	}
</style>
