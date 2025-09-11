<script lang="ts">
	import { Table, TableRow } from '@hyvor/design/components';

	interface Metric {
		name: string;
		labels?: string[];
		description?: string;
		global?: boolean;
	}

	let metrics: Metric[] = [
		{
			name: 'relay_info',
			labels: ['version', 'env', 'instance_domain'],
			description: 'Information about the Relay instance',
			global: true
		}
	];
</script>

<Table columns="1fr 1fr 2fr">
	<TableRow head>
		<div>Metric</div>
		<div>Labels</div>
		<div>Description</div>
	</TableRow>

	{#each metrics as { name, labels = [], description = '', global = false }}
		<TableRow>
			<div>
				<code>{name}</code>
			</div>
			<div class="labels">
				{#each labels as label}
					<code>{label}</code>
				{/each}
			</div>
			<div>
				{description}
			</div>
		</TableRow>
	{/each}
</Table>

<style>
	.labels {
		display: flex;
		gap: 3px;
		flex-wrap: wrap;
	}
</style>
