<script lang="ts">
	import { Table, TableRow } from '@hyvor/design/components';

	const defaultWarmupSchedule: { from: number; to?: number; value: number }[] = [
		{ from: 1, value: 50 },
		{ from: 2, value: 100 },
		{ from: 3, value: 250 },
		{ from: 4, value: 500 },
		{ from: 5, value: 1000 },
		{ from: 6, value: 2500 },
		{ from: 7, value: 5000 },
		{ from: 8, to: 9, value: 10000 },
		{ from: 10, to: 11, value: 20000 },
		{ from: 12, to: 13, value: 40000 },
		{ from: 14, value: 75000 },
		{ from: 15, to: 19, value: 150000 },
		{ from: 20, to: 25, value: 300000 },
		{ from: 26, to: 28, value: 500000 },
		{ from: 29, to: 30, value: 1000000 }
	];
</script>

<h1>Management</h1>

<p>This section covers ongoing management tasks for your Hyvor Relay instance.</p>

<ul>
	<li>
		<a href="#system-project">System Project</a>
	</li>
	<li>
		<a href="#sudo-users">Sudo Users</a>
	</li>
	<li>
		<a href="#ip-warmup">IP Warmup</a>
	</li>
</ul>

<h2 id="system-project">System Project</h2>

<p>
	When Hyvor Relay is initialized, a special read-only <strong>System Project</strong> is
	automatically created using the <a href="#instance-domain">instance domain</a>. It is used for
	sending system emails (e.g. notifications). All sudo users are automatically granted read-only
	access (<code>project.read</code>, <code>sends.read</code>, <code>domains.read</code>,
	<code>analytics.read</code>) and this is kept in sync as sudo users are added or removed.
</p>

<h2 id="sudo-users">Sudo Users</h2>

<p>You can add and remove sudo users from the command line.</p>

<ul>
	<li>SSH into one of the servers.</li>
	<li>
		<code>cd</code> into the Hyvor Relay deployment directory.
	</li>
	<li>
		<code> docker compose exec -it relay bash </code> to enter the app container.
	</li>
	<li>
		Then, use the following commands:
		<ul style="margin-top: 8px">
			<li>
				<code>bin/console sudo:list</code>: List all sudo users.
			</li>
			<li>
				<code>bin/console sudo:add {'<email>'}</code>: Add a new sudo user by email.
			</li>
			<li>
				<code>bin/console sudo:remove {'<id>'}</code>: Remove a sudo user by ID.
			</li>
		</ul>
	</li>
</ul>

<h2 id="ip-warmup">IP Warmup</h2>

<p>
	Email providers may temporarily block or limit emails if they detect a sudden increase in email
	volume from a new IP address. To avoid this, it is recommended to gradually increase the email
	volume over time. This process is known as <strong>IP warmup</strong>.
</p>

<p>
	Hyvor Relay automatically starts the default warmup schedule for new IP addresses, but you can
	customize it in the Sudo &rarr; Servers section.
</p>

<p>Hyvor Relay's default warmup schedule is as follows:</p>

<Table style="bordered" columns="1fr 1fr">
	<TableRow head>
		<div>Day</div>
		<div>Email Volume</div>
	</TableRow>

	{#each defaultWarmupSchedule as schedule}
		<TableRow>
			<div>
				{schedule.from}{schedule.to ? `-${schedule.to}` : ''}
			</div>
			<div>
				{schedule.value}
			</div>
		</TableRow>
	{/each}
</Table>
