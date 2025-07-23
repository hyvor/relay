<script>
	import { CodeBlock } from '@hyvor/design/components';
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
		<a href="#loki">Loki</a>
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
  - job_name: 'hyvor-relay'
    static_configs:
      - targets: 
        - 'your-relay-server-1-private-ip:9667'
        - 'your-relay-server-2-private-ip:9667'
`}
	language="yaml"
/>

<h2 id="grafana">Grafana</h2>

<p>
	First, make sure you have added the Prometheus data source in Grafana. Then, copy the dashboard
	JSON from <a
		href="https://github.com/hyvor/relay/blob/main/meta/monitoring/grafana.json"
		target="_blank">grafana.json</a
	> and import it into your Grafana instance.
</p>

<!-- <p>TODO: add screenshot</p> -->

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

<h2 id="loki">Loki</h2>

<p>
	Log aggregation is an important part of monitoring. This documentation shows how to set up
	<a href="https://grafana.com/oss/loki/" target="_blank">Loki</a> for log aggregation.
</p>

<p>
	Side note: If you already have a log aggregation system in place, such as ELK stack, feel free
	to use that. These are the two log files that you should monitor: : <code
		>/var/log/relay/api.log</code
	>
	and
	<code>/var/log/relay/worker.log</code>. Both files are in JSON format.
</p>

<p>
	<!--  -->
</p>
