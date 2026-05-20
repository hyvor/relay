<script lang="ts">
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import Filters from '$lib/sends/Filters.svelte';
	import SendsList from '$lib/sends/SendsList.svelte';
	import type { Send, SendRecipientStatus, StatusOption } from '$lib/sends/types';
	import { getSends } from '../../lib/actions/emailActions';
	import { emailStore } from '../../lib/stores/projectStore.svelte';
	import { consoleUrlProject } from '../../lib/consoleUrl';

	const STATUS_OPTIONS: StatusOption[] = [
		{ value: null, label: 'All' },
		{ value: 'queued', label: 'Queued' },
		{ value: 'accepted', label: 'Accepted' },
		{ value: 'bounced', label: 'Bounced' }
	];

	const PER_PAGE = 25;

	let status: SendRecipientStatus | null = $state(null);
	let fromSearch: string = $state('');
	let toSearch: string = $state('');
	let subjectSearch: string = $state('');
	let dateFromSearch: string | null = $state(null);
	let dateToSearch: string | null = $state(null);

	let queryKey = $derived(
		[status, fromSearch, toSearch, subjectSearch, dateFromSearch, dateToSearch].join('|')
	);

	function fetchSends(beforeId: number | null): Promise<Send[]> {
		return getSends(
			status,
			fromSearch || null,
			toSearch || null,
			subjectSearch || null,
			dateFromSearch,
			dateToSearch,
			PER_PAGE,
			beforeId
		).then((data) => {
			emailStore.set(data);
			return data;
		});
	}
</script>

<SingleBox>
	<div class="top">
		<div class="left">
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
		hrefBuilder={(s) => consoleUrlProject(`sends/${s.uuid}`)}
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
	.left {
		flex: 1;
		display: flex;
		gap: 10px;
		align-items: center;
	}
</style>
