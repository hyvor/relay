<script lang="ts">
	import { onMount } from 'svelte';
	import { getSudoStats } from '../sudoActions';
	import type { SudoStats, SudoStatsCounts } from '../sudoTypes';
	import { IconMessage, Loader, Button, Tooltip, TabNav, TabNavItem } from '@hyvor/design/components';
	import StatsChart from './StatsChart.svelte';

	type Period = 'today' | '7d' | '30d' | '1y' | 'custom';

	let stats: SudoStats | null = $state(null);
	let loading = $state(true);
	let error = $state('');
	let period: Period = $state('7d');

	function isoDate(d: Date) {
		const p = (n: number) => String(n).padStart(2, '0');
		return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
	}
	const todayIso = isoDate(new Date());
	let customFrom = $state(isoDate(new Date(Date.now() - 13 * 86400000)));
	let customTo = $state(todayIso);
	let customValid = $derived(
		!!customFrom &&
			!!customTo &&
			customFrom <= customTo &&
			(new Date(customTo).getTime() - new Date(customFrom).getTime()) / 86400000 < 366
	);
	let tab: 'overview' | 'ips' | 'projects' | 'domains' = $state('overview');
	let chartMode: 'volume' | 'rates' = $state('volume');

	onMount(load);

	function load() {
		loading = true;
		error = '';
		getSudoStats(period, customFrom, customTo)
			.then((res) => (stats = res))
			.catch((e) => (error = e.message || 'Failed to load stats'))
			.finally(() => (loading = false));
	}

	function setPeriod(p: Period) {
		period = p;
		if (p !== 'custom') load();
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
			if (p.send_recipients < 100) continue;
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
		if (p === 'custom') return stats ? `${stats.start_date} → ${stats.end_date}` : 'custom period';
		return { today: 'today', '7d': 'last 7 days', '30d': 'last 30 days', '1y': 'last year' }[p];
	}
	function prevLabel(p: Period) {
		if (p === 'custom') return 'the previous period of equal length';
		return {
			today: 'yesterday',
			'7d': 'previous 7 days',
			'30d': 'previous 30 days',
			'1y': 'previous year'
		}[p];
	}

	function funnel(t: SudoStatsCounts) {
		const base = t.send_recipients || 1;
		return [
			{ label: 'Submitted', value: t.send_recipients, cls: 'f-grey' },
			{ label: 'Suppressed', value: t.suppressed, cls: 'f-orange' },
			{ label: 'Accepted', value: t.accepted, cls: 'f-green' },
			{ label: 'Deferred', value: t.deferred, cls: 'f-grey' },
			{ label: 'Bounced', value: t.bounced, cls: 'f-red' },
			{ label: 'Complained', value: t.complained, cls: 'f-blue' },
			{ label: 'Failed', value: t.failed, cls: 'f-purple' }
		].map((f) => ({ ...f, width: Math.max(1, (f.value / base) * 100) }));
	}
</script>

<div class="page">
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
		<div class="period-wrap">
			<div class="period-buttons">
				{#each [['today', 'Today'], ['7d', '7d'], ['30d', '30d'], ['1y', '1y'], ['custom', 'Custom']] as [p, label]}
					<Button
						size="x-small"
						variant={period === p ? 'fill' : 'outline'}
						onclick={() => setPeriod(p as Period)}
					>
						{label}
					</Button>
				{/each}
			</div>
			{#if period === 'custom'}
				<form
					class="custom-range"
					onsubmit={(e) => {
						e.preventDefault();
						if (customValid) load();
					}}
				>
					<input type="date" bind:value={customFrom} max={customTo || todayIso} />
					<span>→</span>
					<input type="date" bind:value={customTo} min={customFrom} max={todayIso} />
					<Button size="x-small" type="submit" disabled={!customValid}>Apply</Button>
				</form>
				{#if !customValid}
					<div class="range-hint">Pick a valid range (max 366 days)</div>
				{/if}
			{/if}
		</div>
	</div>

	<div class="tabs">
		<TabNav>
			<TabNavItem name="overview" active={tab === 'overview'} onclick={() => (tab = 'overview')}>
				Overview
			</TabNavItem>
			<TabNavItem name="ips" active={tab === 'ips'} onclick={() => (tab = 'ips')}>
				IP Addresses
			</TabNavItem>
			<TabNavItem name="projects" active={tab === 'projects'} onclick={() => (tab = 'projects')}>
				Projects
			</TabNavItem>
			<TabNavItem name="domains" active={tab === 'domains'} onclick={() => (tab = 'domains')}>
				Recipient Domains
			</TabNavItem>
		</TabNav>
	</div>

	{#if loading && !stats}
		<Loader block padding={55} />
	{:else if error}
		<IconMessage error message={error} />
	{:else if stats}
		{@const t = stats.totals}
		{@const pt = stats.previous_totals}

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

		<div class="content" class:loading>
			{#if tab === 'overview'}
				<section class="section s-alerts">
					<div class="section-title">Alerts</div>
					<div class="alerts">
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
					</div>
				</section>

				<section class="section s-volume">
					<div class="section-title">Volume</div>
					<div class="kpis">
						{@render kpi('Emails submitted', compactNum(t.send_recipients), 'ok', delta(t.send_recipients, pt.send_recipients, false), 'Recipients accepted into the queue (including suppressed ones)')}
						{@render kpi('Active projects', num(t.projects), 'ok', delta(t.projects, pt.projects, false), 'Projects that submitted at least one email')}
						{@render kpi('Delivered', pct(t.rates.accepted, 1), 'ok', delta(t.rates.accepted, pt.rates.accepted, false), 'Accepted by the receiving server ÷ submitted')}
						{@render kpi('Suppressed', pct(t.rates.suppressed, 1), 'ok', delta(t.rates.suppressed, pt.rates.suppressed), 'Recipients skipped because they are on a suppression list')}
					</div>
				</section>

				<section class="section s-health">
					<div class="section-title">Reputation</div>
					<div class="kpis">
						{@render kpi('Bounce rate', pct(t.rates.bounce), bounceLevel(t.rates.bounce), delta(t.rates.bounce, pt.rates.bounce), 'All bounces ÷ submitted. < 2% great, ≥ 5% warning, ≥ 10% critical')}
						{@render kpi('Complaint rate', pct(t.rates.complaint), complaintLevel(t.rates.complaint), delta(t.rates.complaint, pt.rates.complaint), 'Spam complaints ÷ (accepted + complained). Keep < 0.10%; Gmail/Yahoo enforce 0.30%')}
						{@render kpi('Infra bounces', pct(t.rates.bounce_infrastructure), infraLevel(t.rates.bounce_infrastructure), delta(t.rates.bounce_infrastructure, pt.rates.bounce_infrastructure), 'Bounces caused by our infrastructure (blocklists, PTR, throttling). Should be ~0%')}
						{@render kpi('Deferred', pct(t.rates.deferred, 1), deferLevel(t.rates.deferred), delta(t.rates.deferred, pt.rates.deferred), 'Recipients still being retried (receivers throttling us)')}
					</div>
				</section>

				<section class="section s-trend">
					<div class="section-head">
						<div class="section-title">Trend</div>
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
					<div class="section-body">
						<StatsChart data={stats.daily} mode={chartMode} />
					</div>
				</section>

				<section class="section s-funnel">
					<div class="section-title">
						Delivery funnel <span class="muted">({periodLabel(period)})</span>
					</div>
					<div class="section-body">
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
							<span class={infraLevel(t.rates.bounce_infrastructure)}
								>infrastructure <b>{num(t.bounced_infrastructure)}</b> ({pct(t.rates.bounce_infrastructure)})</span
							>
							<span>unknown <b>{num(t.bounced_unknown)}</b> ({pct(t.rates.bounce_unknown)})</span>
						</div>
					</div>
				</section>
			{:else if tab === 'ips'}
				<section class="section s-ips">
					<div class="section-title">
						IP addresses <span class="muted">({periodLabel(period)})</span>
					</div>
					<div class="section-body table-wrap">
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
										<td class="r {infraLevel(ip.rates.bounce_infrastructure)}">{pct(ip.rates.bounce_infrastructure)}</td>
										<td class="r {complaintLevel(ip.rates.complaint)}">{pct(ip.rates.complaint)}</td>
										<td class={ip.ptr_valid ? 'ok-text' : 'bad'}>{ip.ptr_valid ? 'valid' : 'invalid'}</td>
									</tr>
								{:else}
									<tr><td colspan="9" class="empty">No IP addresses</td></tr>
								{/each}
							</tbody>
						</table>
					</div>
				</section>
			{:else if tab === 'projects'}
				<section class="section s-projects">
					<div class="section-title">
						Projects <span class="muted">(by volume, {periodLabel(period)})</span>
					</div>
					<div class="section-body table-wrap">
						<table>
							<thead>
								<tr>
									<th>Project</th>
									<th class="r">Submitted</th>
									<th class="r">Delivered</th>
									<th class="r">Bounce</th>
									<th class="r">Complaint</th>
									<th class="r">Suppressed</th>
								</tr>
							</thead>
							<tbody>
								{#each stats.projects as p}
									<tr>
										<td><a href="/sudo/projects/{p.id}">{p.name}</a></td>
										<td class="r">{num(p.send_recipients)}</td>
										<td class="r">{pct(p.rates.accepted, 1)}</td>
										<td class="r {bounceLevel(p.rates.bounce)}">{pct(p.rates.bounce)}</td>
										<td class="r {complaintLevel(p.rates.complaint)}">{pct(p.rates.complaint)}</td>
										<td class="r">{pct(p.rates.suppressed, 1)}</td>
									</tr>
								{:else}
									<tr><td colspan="6" class="empty">No sends in this period</td></tr>
								{/each}
							</tbody>
						</table>
					</div>
				</section>
			{:else}
				<section class="section s-domains">
					<div class="section-title">
						Recipient domains <span class="muted">(top 10, {periodLabel(period)})</span>
					</div>
					<div class="section-body table-wrap">
						<table>
							<thead>
								<tr>
									<th>Domain</th>
									<th class="r">Attempted</th>
									<th class="r">Delivered</th>
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
										<td class="r">{pct(d.rates.accepted, 1)}</td>
										<td class="r {bounceLevel(d.rates.bounce)}">{pct(d.rates.bounce)}</td>
										<td class="r {infraLevel(d.rates.bounce_infrastructure)}">{pct(d.rates.bounce_infrastructure)}</td>
										<td class="r {complaintLevel(d.rates.complaint)}">{pct(d.rates.complaint)}</td>
									</tr>
								{:else}
									<tr><td colspan="6" class="empty">No data</td></tr>
								{/each}
							</tbody>
						</table>
					</div>
				</section>
			{/if}
		</div>
	{/if}
</div>

<style>
	.page {
		display: flex;
		flex-direction: column;
		flex: 1;
		min-height: 0;
		overflow: auto;
	}
	.header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		padding: 24px 40px 12px;
	}
	h2 {
		margin: 0;
		font-size: 22px;
	}
	.sub,
	.muted {
		color: var(--text-light);
		font-size: 13px;
		font-weight: normal;
	}
	.period-wrap {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 8px;
	}
	.custom-range {
		display: flex;
		align-items: center;
		gap: 6px;
	}
	.custom-range input {
		background: var(--input);
		border: 1px solid var(--border);
		border-radius: 20px;
		padding: 3px 10px;
		font-size: 13px;
		color: var(--text);
		font-family: inherit;
	}
	.range-hint {
		font-size: 12px;
		color: var(--red);
	}
	.period-buttons,
	.toggle {
		display: flex;
		gap: 5px;
	}
	.tabs {
		padding: 0 30px;
		border-bottom: 1px solid var(--border);
	}
	.content.loading {
		opacity: 0.5;
	}

	/* sections: full-width, bordered, tinted */
	.section {
		border-bottom: 1px solid var(--border);
	}
	.section-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-right: 40px;
	}
	.section-title {
		font-size: 18px;
		font-weight: 600;
		padding: 20px 40px 0;
	}
	.section-body {
		padding: 16px 40px 24px;
	}

	/* alerts */
	.alerts {
		display: flex;
		flex-direction: column;
		gap: 6px;
		padding: 14px 40px 20px;
	}
	.alert {
		padding: 8px 14px;
		border-left: 3px solid;
		font-size: 14px;
	}
	.alert.ok {
		border-color: var(--green);
		background: var(--green-light);
	}
	.alert.warn {
		border-color: var(--orange);
		background: var(--orange-light);
	}
	.alert.bad {
		border-color: var(--red);
		background: var(--red-light);
	}

	/* kpis: flat cells separated by dividers */
	.kpis {
		display: flex;
		flex-wrap: wrap;
		padding: 4px 8px 8px;
	}
	.kpi {
		flex: 1;
		min-width: 200px;
		padding: 14px 32px;
	}
	.kpi + .kpi {
		border-left: 1px solid var(--border);
	}
	.kpi-title {
		font-size: 13px;
		color: var(--text-light);
		cursor: help;
		margin-bottom: 4px;
	}
	.kpi-value {
		font-size: 28px;
		font-weight: 600;
	}
	.kpi-delta {
		font-size: 12px;
		margin-top: 2px;
		color: var(--text-light);
	}

	.warn {
		color: var(--orange);
	}
	.bad {
		color: var(--red);
	}
	.good,
	.ok-text {
		color: var(--green);
	}
	.badc {
		color: var(--red);
	}
	.alert.warn,
	.alert.bad {
		color: inherit;
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
		height: 14px;
		overflow: hidden;
	}
	.funnel-bar {
		height: 100%;
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
		margin-top: 14px;
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
		font-weight: 500;
		font-size: 12px;
		color: var(--text-light);
		padding: 4px 8px;
		border-bottom: 1px solid var(--border);
		white-space: nowrap;
	}
	td {
		padding: 8px;
	}
	tr:not(:last-child) td {
		border-bottom: 1px solid var(--border);
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
