<script>
	import { Table, TableRow } from '@hyvor/design/components';
</script>

<h1>Prod Deploy</h1>

<p>
	This page covers a <strong>production-ready deployment</strong> that requires multiple servers.
	If you want to deploy Hyvor Relay on for hobby or small to medium-sized projects (less than
	100,000 emails/day), refer to the
	<a href="/hosting/deploy-easy">Easy Deploy</a> page.
</p>

<h2 id="infra">Infrastructure</h2>

<ul>
	<li>
		<a href="#pgsql">PostgreSQL</a>
	</li>
	<li>
		<a href="#app-servers"> App Servers </a> (1 or more)
		<ul style="margin-top: 8px">
			<li>
				<a href="#how-many">How many servers?</a>
			</li>
			<li>
				<a href="#ansible"> Setting up with Ansible (recommended) </a>
			</li>
		</ul>
	</li>
</ul>

<h3 id="pgsql">PostgreSQL</h3>

<p>
	Hyvor Relay uses PostgreSQL as the database and also as the message queue. Set up a PostgreSQL
	server in a production-ready manner. Hyvor Relay has been tested with PostgreSQL 16. If your
	cloud provider offers a managed PostgreSQL service, feel free to use it. It will make backups,
	failover, and scaling easier. Otherwise, self-host PostgreSQL with high availability in mind.
</p>

<p>
	Since setting up a PostgreSQL server depends a bit on how your infrastructure is set up, we will
	not go into details here. Whichever option you choose, make sure that:
</p>

<ul>
	<li>
		A dedicated database is created for Hyvor Relay. Recommended name: <code>hyvor_relay</code>.
	</li>
	<li>
		A dedicated user is created with all privileges on the Hyvor Relay database and a strong
		password.
	</li>
	<li>
		The PostgreSQL server is configured to allow connections from the app servers (ideally via a
		private network).
	</li>
	<li>Backup strategies are in place.</li>
</ul>

<h2 id="app-servers">App Servers</h2>

<h3 id="how-many">How many servers?</h3>

<p>
	The number of app servers you need depends on your expected email volume. Here are some rough
	guidelines, which are mostly on the safer side:
</p>

<Table columns="1fr 1fr">
	<TableRow head>
		<div>Servers</div>
		<div>Expected Email Volume</div>
	</TableRow>
	<TableRow>
		<div>1 server, 4GB RAM, 2 CPUs</div>
		<div>1,000,000 emails/day</div>
	</TableRow>
	<TableRow>
		<div>2 servers, 8GB RAM, 4 CPUs</div>
		<div>10,000,000 emails/day</div>
	</TableRow>
</Table>

<p>
	See the <a href="/hosting/scaling">Scaling & HA</a> page for more insights to make a better estimate.
</p>

<!-- 

<h3 id="private-network">Private Network</h3>

<p>
	Hyvor Relay app servers communicate with each other for various tasks, such as propagating
	configuration changes. Therefore, all app servers should be in a private network.
</p>

<p>
	The default CIDR for the private network is <code>10.0.0.0/8</code>. So, whenever possible, we
	recommend using a private IP address in that range for the app servers. If it is not possible to
	use that CIDR (e.g., if you are using a cloud provider that does not allow it or if you have
	other constraints), then use any other private CIDR range. Later, you can
	<a href="/hosting/setup#private-network">configure Hyvor Relay to use that range</a>.
</p>

<h3 id="app-server-setup">App Server Setup</h3>

<p>
	We recommend using a Linux distribution like Ubuntu or Debian for the app servers. Our Cloud
	runs on Ubuntu 24.04 LTS. Each server should have <strong>Docker</strong> installed.
</p> -->

<!-- 

Compose file

services:
  relay:
    image: hyvor/relay:latest
    deploy:
      mode: global
    networks:
      - hostnet
    labels:
      app: relay

networks:
  hostnet:
    name: "host"
    external: true
-->
