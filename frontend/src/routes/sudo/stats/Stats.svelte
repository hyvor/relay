<script lang="ts">
	import { onMount } from 'svelte';
	import { getSudoStats } from '../sudoActions';
	import type { SudoStats, SudoStatsCounts } from '../sudoTypes';
	import { IconMessage, Loader, Button, Tooltip } from '@hyvor/design/components';
	import StatsChart from './StatsChart.svelte';

	type Period = 'today' | '7d' | '30d';

	let stats: SudoStats | null = $state(null);
	let loading = $state(true);
	let error = $state('');
	let period: Period = $state('7d');
	let chartMode: 'volume' | 'rates' = $state('volume');

	onMount(load);

	function load() {
		loading = true;
		error = '';
		getSudoStats(period)
			.then((res) => (stats = res))
			.catch((e) => (error = e.message || 'Failed to load stats'))
			.finally(() => (loading = false));
	}

	function setPeriod(p: Period) {
		period = p;
		load();
	}

	// ---- formatting ----
	const nf = new Intl.NumberFormat('en-US');
	const num = (n: number) => nf.format(n || 0);
	const pct = (r: number, digits = 2) => ((r || 0) * 100).toFixed(digits) + '%';

	function compactNum(n: number) {
		return new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(
			n || 0
		);
	}

	// ---- health thresholds (industry guidance: Gmail/Yahoo want < 0.1% spam, bounces < 2-5%) ----
	type Level = 'ok' | 'warn' | 'bad';

	function bounceLevel(r: number): Level {
		return r >= 0.1 ? 'bad' : r >= 0.05 ? 'warn' : 'ok';
	}
	function complaintLevel(r: number): Level {
		return r >= 0.003 ? 'bad' : r >= 0.001 ? 'warn' : 'ok';
	}
	function infraLevel(r: number): Level {
		return r >= 0.03 ? 'bad' : r >= 0.01 ? 'warn' : 'ok';
	}
	function deferLevel(r: number): Level {
		return r >= 0.2 ? 'bad' : r >= 0.1 ? 'warn' : 'ok';
	}

	/** change vs previous period; `lowerIsBetter` flips the color */
	function delta(cur: number, prev: number, lowerIsBetter = true) {
		if (!prev && !cur) return null;
		if (!prev) return { text: 'new', cls: '' };
		const change = (cur - prev) / prev;
		if (Math.abs(change) < 0.005) return { text: '±0%', cls: '' };
		const up = change > 0;
		const good = lowerIsBetter ? !up : up;
		return {
			text: (up ? '▲ ' : '▼ ') + Math.abs(change * 100).toFixed(0) + '%',
			cls: good ? 'good' : 'badc'
		};
	}

	// ---- alerts ----
	interface Alert {
		level: Level;
		text: string;
	}

	let alerts: Alert[] = $derived.by(() => {
		if (!stats) return [];
		const out: Alert[] = [];
		const t = stats.totals;

		if (complaintLevel(t.rates.complaint) !== 'ok') {
			out.push({
				level: complaintLevel(t.rates.complaint),
				text: `Overall complaint rate is ${pct(t.rates.complaint)} (Gmail/Yahoo require < 0.30%, aim for < 0.10%).`
			});
		}
		if (bounceLevel(t.rates.bounce) !== 'ok') {
			out.push({
				level: bounceLevel(t.rates.bounce),
				text: `Overall bounce rate is ${pct(t.rates.bounce)}.`
			});
		}
		if (infraLevel(t.rates.bounce_infrastructure) !== 'ok') {
			out.push({
				level: infraLevel(t.rates.bounce_infrastructure),
				text: `Infrastructure bounces are ${pct(t.rates.bounce_infrastructure)} of attempts. This is on us (blocklists, bad PTR, throttling), not senders.`
			});
		}
		for (const ip of stats.ips) {
			if (ip.attempted < 100) continue;
			if (infraLevel(ip.rates.bounce_infrastructure) !== 'ok' || deferLevel(ip.rates.deferred) !== 'ok') {
				out.push({
					level: 'bad',
					text: `IP ${ip.ip} (${ip.server}) looks unhealthy: ${pct(ip.rates.bounce_infrastructure)} infra bounces, ${pct(ip.rates.deferred, 1)} deferred.`
				});
			}
			if (!ip.ptr_valid) {
				out.push({ level: 'warn', text: `IP ${ip.ip} (${ip.server}) has invalid PTR records.` });
			}
		}
		for (const p of stats.projects) {
			if (p.attempted < 100) continue;
			if (complaintLevel(p.rates.complaint) === 'bad') {
				out.push({
					level: 'bad',
					text: `Project "${p.name}" has a ${pct(p.rates.complaint)} complaint rate.`
				});
			} else if (bounceLevel(p.rates.bounce) === 'bad') {
				out.push({
					level: 'warn',
					text: `Project "${p.name}" has a ${pct(p.rates.bounce)} bounce rate.`
				});
			}
		}
		return out.sort((a, b) => (a.level === b.level ? 0 : a.level === 'bad' ? -1 : 1));
	});

	function periodLabel(p: Period) {
		return { today: 'today', '7d': 'last 7 days', '30d': 'last 30 days' }[p];
	}
	function prevLabel(p: Period) {
		return { today: 'yesterday', '7d': 'previous 7 days', '30d': 'previous 30 days' }[p];
	}

	function funnel(t: SudoStatsCounts) {
		const base = t.send_recipients || 1;
		return [
			{ label: 'Submitted', value: t.send_recipients, cls: 'f-grey' },
			{ label: 'Suppressed', value: t.suppressed, cls: 'f-orange' },
			{ label: 'Attempted', value: t.attempted, cls: 'f-blue' },
			{ label: 'Accepted', value: t.accepted, cls: 'f-green' },
			{ label: 'Deferred', value: t.deferred, cls: 'f-grey' },
			{ label: 'Bounced', value: t.bounced, cls: 'f-red' },
			{ label: 'Failed', value: t.failed, cls: 'f-purple' }
		].map((f) => ({ ...f, width: Math.max(1, (f.value / base) * 100) }));
	}
</script>

<div class="wrap">
	<div class="header">
		<div>
			<h2>Sending health</h2>
			{#if stats}
				<div class="sub">
					{stats.start_date === stats.end_date
						? stats.start_date
						: `${stats.start_date} → ${stats.end_date}`} · compared with {prevLabel(period)}
				</div>
			{/if}
		</div>
		<div class="period-buttons">
			{#each ['today', '7d', '30d'] as p}
				<Button
					size="x-small"
					variant={period === p ? 'fill' : 'outline'}
					onclick={() => setPeriod(p as Period)}
				>
					{p === 'today' ? 'Today' : p}
				</Button>
			{/each}
		</div>
	</div>

	{#if loading && !stats}
		<Loader block padding={55} />
	{:else if error}
		<IconMessage error message={error} />
	{:else if stats}
		{@const t = stats.totals}
		{@const pt = stats.previous_totals}

		<!-- alerts -->
		<section class="alerts">
			{#if alerts.length === 0}
				<div class="alert ok">✓ Everything looks healthy for {periodLabel(period)}.</div>
			{:else}
				{#each alerts as a}
					<div class="alert {a.level}">
						{a.level === 'bad' ? '●' : '▲'}
						{a.text}
					</div>
				{/each}
			{/if}
		</section>

		<!-- KPIs -->
		<section class="kpis" class:loading>
			{#snippet kpi(title: string, value: string, level: Level, d: ReturnType<typeof delta>, hint: string)}
				<div class="kpi">
					<div class="kpi-title">
						<Tooltip text={hint} position="bottom"><span>{title}</span></Tooltip>
					</div>
					<div class="kpi-value {level}">{value}</div>
					<div class="kpi-delta">
						{#if d}<span class={d.cls}>{d.text}</span>{:else}&nbsp;{/if}
					</div>
				</div>
			{/snippet}

			{@render kpi(
				'Emails submitted',
				compactNum(t.send_recipients),
				'ok',
				delta(t.send_recipients, pt.send_recipients, false),
				'Recipients accepted into the queue (including suppressed ones)'
			)}
			{@render kpi(
				'Active projects',
				num(t.projects),
				'ok',
				delta(t.projects, pt.projects, false),
				'Projects that submitted at least one email'
			)}
			{@render kpi(
				'Delivered',
				pct(t.rates.accepted, 1),
				'ok',
				delta(t.rates.accepted, pt.rates.accepted, false),
				'Accepted by the receiving server ÷ attempted'
			)}
			{@render kpi(
				'Bounce rate',
				pct(t.rates.bounce),
				bounceLevel(t.rates.bounce),
				delta(t.rates.bounce, pt.rates.bounce),
				'All bounces ÷ attempted. < 2% great, ≥ 5% warning, ≥ 10% critical'
			)}
			{@render kpi(
				'Complaint rate',
				pct(t.rates.complaint),
				complaintLevel(t.rates.complaint),
				delta(t.rates.complaint, pt.rates.complaint),
				'Spam complaints ÷ accepted. Keep < 0.10%; Gmail/Yahoo enforce 0.30%'
			)}
			{@render kpi(
				'Infra bounces',
				pct(t.rates.bounce_infrastructure),
				infraLevel(t.rates.bounce_infrastructure),
				delta(t.rates.bounce_infrastructure, pt.rates.bounce_infrastructure),
				'Bounces caused by our infrastructure (blocklists, PTR, throttling). Should be ~0%'
			)}
			{@render kpi(
				'Deferred',
				pct(t.rates.deferred, 1),
				deferLevel(t.rates.deferred),
				delta(t.rates.deferred, pt.rates.deferred),
				'Recipients still being retried (receivers throttling us)'
			)}
			{@render kpi(
				'Suppressed',
				pct(t.rates.suppressed, 1),
				'ok',
				delta(t.rates.suppressed, pt.rates.suppressed),
				'Recipients skipped because they are on a suppression list'
			)}
		</section>

		<!-- trend -->
		<section class="card">
			<div class="card-head">
				<h3>Trend</h3>
				<div class="toggle">
					<Button
						size="x-small"
						variant={chartMode === 'volume' ? 'fill' : 'outline'}
						onclick={() => (chartMode = 'volume')}>Volume</Button
					>
					<Button
						size="x-small"
						variant={chartMode === 'rates' ? 'fill' : 'outline'}
						onclick={() => (chartMode = 'rates')}>Rates</Button
					>
				</div>
			</div>
			<StatsChart data={stats.daily} mode={chartMode} />
		</section>

		<!-- funnel -->
		<section class="card">
			<h3>Delivery funnel <span class="muted">({periodLabel(period)})</span></h3>
			<div class="funnel">
				{#each funnel(t) as f}
					<div class="funnel-row">
						<div class="funnel-label">{f.label}</div>
						<div class="funnel-bar-wrap">
							<div class="funnel-bar {f.cls}" style="width: {f.width}%"></div>
						</div>
						<div class="funnel-value">{num(f.value)}</div>
					</div>
				{/each}
			</div>
			<div class="bounce-split">
				Bounces:
				<span>recipient <b>{num(t.bounced_recipient)}</b> ({pct(t.rates.bounce_recipient)})</span>
				<span
					class={infraLevel(t.rates.bounce_infrastructure)}
					>infrastructure <b>{num(t.bounced_infrastructure)}</b> ({pct(
						t.rates.bounce_infrastructure
					)})</span
				>
				<span>unknown <b>{num(t.bounced_unknown)}</b> ({pct(t.rates.bounce_unknown)})</span>
			</div>
		</section>

		<!-- IPs -->
		<section class="card">
			<h3>IP addresses <span class="muted">({periodLabel(period)})</span></h3>
			<div class="table-wrap">
				<table>
					<thead>
						<tr>
							<th>IP</th>
							<th>Server</th>
							<th class="r">Attempted</th>
							<th class="r">Delivered</th>
							<th class="r">Deferred</th>
							<th class="r">Bounce</th>
							<th class="r">Infra</th>
							<th class="r">Complaint</th>
							<th>PTR</th>
						</tr>
					</thead>
					<tbody>
						{#each stats.ips as ip}
							<tr>
								<td class="mono">{ip.ip}</td>
								<td>{ip.server}</td>
								<td class="r">{num(ip.attempted)}</td>
								<td class="r">{pct(ip.rates.accepted, 1)}</td>
								<td class="r {deferLevel(ip.rates.deferred)}">{pct(ip.rates.deferred, 1)}</td>
								<td class="r {bounceLevel(ip.rates.bounce)}">{pct(ip.rates.bounce)}</td>
								<td class="r {infraLevel(ip.rates.bounce_infrastructure)}"
									>{pct(ip.rates.bounce_infrastructure)}</td
								>
								<td class="r {complaintLevel(ip.rates.complaint)}">{pct(ip.rates.complaint)}</td>
								<td class={ip.ptr_valid ? 'ok' : 'bad'}>{ip.ptr_valid ? 'valid' : 'invalid'}</td>
							</tr>
						{:else}
							<tr><td colspan="9" class="empty">No IP addresses</td></tr>
						{/each}
					</tbody>
				</table>
			</div>
		</section>

		<div class="two-col">
			<!-- projects -->
			<section class="card">
				<h3>Projects <span class="muted">(by volume, {periodLabel(period)})</span></h3>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>Project</th>
								<th class="r">Submitted</th>
								<th class="r">Bounce</th>
								<th class="r">Complaint</th>
								<th class="r">Suppr.</th>
							</tr>
						</thead>
						<tbody>
							{#each stats.projects.slice(0, 10) as p}
								<tr>
									<td><a href="/sudo/projects/{p.id}">{p.name}</a></td>
									<td class="r">{num(p.send_recipients)}</td>
									<td class="r {bounceLevel(p.rates.bounce)}">{pct(p.rates.bounce)}</td>
									<td class="r {complaintLevel(p.rates.complaint)}">{pct(p.rates.complaint)}</td>
									<td class="r">{pct(p.rates.suppressed, 1)}</td>
								</tr>
							{:else}
								<tr><td colspan="5" class="empty">No sends in this period</td></tr>
							{/each}
						</tbody>
					</table>
				</div>
			</section>

			<!-- domains -->
			<section class="card">
				<h3>Recipient domains <span class="muted">(top 10, {periodLabel(period)})</span></h3>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>Domain</th>
								<th class="r">Attempted</th>
								<th class="r">Bounce</th>
								<th class="r">Infra</th>
								<th class="r">Complaint</th>
							</tr>
						</thead>
						<tbody>
							{#each stats.domains as d}
								<tr>
									<td>{d.domain}</td>
									<td class="r">{num(d.attempted)}</td>
									<td class="r {bounceLevel(d.rates.bounce)}">{pct(d.rates.bounce)}</td>
									<td class="r {infraLevel(d.rates.bounce_infrastructure)}"
										>{pct(d.rates.bounce_infrastructure)}</td
									>
									<td class="r {complaintLevel(d.rates.complaint)}">{pct(d.rates.complaint)}</td>
								</tr>
							{:else}
								<tr><td colspan="5" class="empty">No data</td></tr>
							{/each}
						</tbody>
					</table>
				</div>
			</section>
		</div>
	{/if}
</div>

<style>
	.wrap {
		padding: 25px 40px 40px;
		overflow: auto;
		display: flex;
		flex-direction: column;
		gap: 22px;
	}
	.header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
	}
	h2 {
		margin: 0;
		font-size: 22px;
	}
	h3 {
		margin: 0 0 12px;
		font-size: 16px;
	}
	.sub,
	.muted {
		color: var(--text-light);
		font-size: 13px;
		font-weight: normal;
	}
	.period-buttons,
	.toggle {
		display: flex;
		gap: 5px;
	}

	/* alerts */
	.alerts {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}
	.alert {
		padding: 9px 14px;
		border-radius: var(--box-radius, 10px);
		font-size: 14px;
	}
	.alert.ok {
		background: var(--green-light);
		color: var(--green-dark, inherit);
	}
	.alert.warn {
		background: var(--orange-light);
	}
	.alert.bad {
		background: var(--red-light);
	}

	/* kpis */
	.kpis {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
		gap: 14px;
	}
	.kpis.loading {
		opacity: 0.5;
	}
	.kpi {
		border: 1px solid var(--border);
		border-radius: var(--box-radius, 10px);
		padding: 14px 16px;
	}
	.kpi-title {
		font-size: 13px;
		color: var(--text-light);
		cursor: help;
	}
	.kpi-value {
		font-size: 28px;
		font-weight: 600;
		margin: 4px 0 2px;
	}
	.kpi-delta {
		font-size: 12px;
		color: var(--text-light);
	}

	.ok {
		color: inherit;
	}
	.warn {
		color: var(--orange);
	}
	.bad {
		color: var(--red);
	}
	.good {
		color: var(--green);
	}
	.badc {
		color: var(--red);
	}
	td.ok {
		color: var(--green);
	}

	/* cards */
	.card {
		border: 1px solid var(--border);
		border-radius: var(--box-radius, 10px);
		padding: 18px 20px;
	}
	.card-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 12px;
	}
	.card-head h3 {
		margin: 0;
	}
	.two-col {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
		gap: 22px;
	}

	/* funnel */
	.funnel {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}
	.funnel-row {
		display: grid;
		grid-template-columns: 90px 1fr 90px;
		align-items: center;
		gap: 10px;
		font-size: 13px;
	}
	.funnel-bar-wrap {
		background: var(--hover, #f4f4f4);
		border-radius: 4px;
		height: 14px;
		overflow: hidden;
	}
	.funnel-bar {
		height: 100%;
		border-radius: 4px;
	}
	.funnel-value {
		text-align: right;
		font-variant-numeric: tabular-nums;
	}
	.f-grey {
		background: #b5b5b5;
	}
	.f-green {
		background: #7fb77f;
	}
	.f-red {
		background: #e06666;
	}
	.f-orange {
		background: #e0a23b;
	}
	.f-blue {
		background: #6b9bd1;
	}
	.f-purple {
		background: #9b6bd1;
	}
	.bounce-split {
		margin-top: 12px;
		font-size: 13px;
		color: var(--text-light);
		display: flex;
		gap: 18px;
		flex-wrap: wrap;
	}

	/* tables */
	.table-wrap {
		overflow-x: auto;
	}
	table {
		width: 100%;
		border-collapse: collapse;
		font-size: 14px;
	}
	th {
		text-align: left;
		font-weight: 600;
		font-size: 12px;
		color: var(--text-light);
		padding: 6px 10px;
		border-bottom: 1px solid var(--border);
		white-space: nowrap;
	}
	td {
		padding: 8px 10px;
		border-bottom: 1px solid var(--border);
	}
	tr:last-child td {
		border-bottom: none;
	}
	.r {
		text-align: right;
		font-variant-numeric: tabular-nums;
	}
	.mono {
		font-family: monospace;
	}
	.empty {
		text-align: center;
		color: var(--text-light);
		padding: 20px;
	}
</style>
