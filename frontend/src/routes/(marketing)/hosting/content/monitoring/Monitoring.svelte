<script>
	import { Button, CodeBlock, Table, TableRow } from '@hyvor/design/components';
	import MetricsTable from './MetricsTable.svelte';
</script>

<h1>Monitoring</h1>

<p>Hyvor Relay integrates with the Prometheus/Grafana stack for monitoring.</p>

<ul>
	<li>
		<a href="#prometheus">Prometheus</a>
	</li>
	<li>
		<a href="#grafana">Grafana</a>
	</li>
	<li>
		<a href="#alertmanager">Alertmanager</a>
	</li>
	<li>
		<a href="#logs">Logs</a>
	</li>
</ul>

<h2 id="prometheus">Prometheus</h2>

<p>
	Hyvor Relay exposes metrics in the Prometheus format on port <code>9667</code> on each server.
	It only serves metrics to connections from
	<a href="https://en.wikipedia.org/wiki/Private_network#Private_IPv4_addresses" target="_blank"
		>private</a
	>
	and
	<a
		href="https://en.wikipedia.org/wiki/Private_network#Dedicated_space_for_carrier-grade_NAT_deployment"
		target="_blank">CGNAT</a
	> IP addresses, assuming your monitoring system is on a private network.
</p>

<p>
	To get started, you can add the following to your <code>prometheus.yaml</code> file:
</p>

<CodeBlock
	code={`
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'hyvor-relay'	// Job name must start with 'hyvor-relay'
    static_configs:
      - targets: 
        - 'your-relay-server-1-private-ip:9667'
        - 'your-relay-server-2-private-ip:9667'
`}
	language="yaml"
/>

<p>
	The following metrics are available from each Relay server. Metrics marked as "global" are only
	exposed from the Leader server.
</p>

<MetricsTable />

<h2 id="grafana">Grafana</h2>

<p>
	First, make sure you have added the Prometheus data source in Grafana. Then, copy the dashboard
	JSON from <a
		href="https://github.com/hyvor/relay/blob/main/meta/monitoring/grafana.json"
		target="_blank">grafana.json</a
	> and import it into your Grafana instance.
</p>

<h2 id="alertmanager">Alertmanager</h2>

<p>
	You can set up <a
		href="https://prometheus.io/docs/alerting/latest/alertmanager/"
		target="_blank">Alertmanager</a
	>
	to receive alerts when certain conditions are met in your Relay instance. As a starting point, you
	can use our default
	<a
		href="https://github.com/hyvor/relay/blob/main/meta/monitoring/alertmanager.yaml"
		target="_blank">alertmanager.yaml</a
	> file. You can customize it to suit your needs.
</p>

<h2 id="logs">Logs</h2>

<p>
	By default, Hyvor Relay container writes logs to stdout/stderr. In Docker, you can view the logs
	using the following command:
</p>

<CodeBlock code="docker logs -f relay" />

<p>
	You can forward these logs to your log aggregation system using a Docker plugin or another log
	collector.
</p>

<h3 id="loki">Loki</h3>

<p>
	Here is an example Docker Compose configuration for forwarding logs to Loki. First, install the
	Loki Docker plugin:
</p>

<CodeBlock
	code={`
arch=$(uname -m)
docker plugin install grafana/loki-docker-driver:3.3.2-$\{arch\} --alias loki --grant-all-permissions
`}
/>

<p>
	Then, update your <code>compose.yaml</code> to include the logging configuration:
</p>

<CodeBlock
	code={`
app:
  logging:
	driver: loki
	options:
	  loki-url: "https://mylokiurl.com/loki/api/v1/push"
	  loki-retries: "2"
	  loki-max-backoff: "800ms"
	  loki-timeout: "1s"
	  keep-file: "true"
	  mode: non-blocking
`}
	language="yaml"
/>

<p>
	Make sure to replace the <code>loki-url</code> and restart your Docker containers.
</p>
