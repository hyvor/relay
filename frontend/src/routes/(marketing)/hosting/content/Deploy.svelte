<script>
	import { Table, TableRow } from '@hyvor/design/components';
</script>

<h1>Deploy</h1>

<p>
	This page covers a <strong>production-ready deployment</strong> that requires multiple servers.
	If you want to deploy Hyvor Relay on for hobby or small projects (less than 25,000 emails/day),
	please refer to the
	<a href="/hosting/deploy-easy">Easy Deploy</a> page which covers a single server deployment.
</p>

<h2 id="infra">Infrastructure</h2>

<p>Servers:</p>

<ul>
	<li>
		<a href="#pgsql">PostgreSQL</a>
	</li>
	<li>
		<a href="#app-server"> App Server </a> (1 or more)
	</li>
</ul>

<h3 id="pgsql">PostgreSQL</h3>

<p>
	Hyvor Relay uses PostgreSQL as the database and also as the message queue. Set up a PostgreSQL
	server in a production-ready manner. We have tested Hyvor Relay with PostgreSQL 16.
</p>

<ul>
	<li>
		<strong>Cloud Provider</strong>: If your cloud provider offers a managed PostgreSQL service,
		feel free to use it. It will make backups, failover, and scaling easier.
	</li>
	<li>
		<strong>Self-Hosted PostgreSQL</strong>: This is the other option. Additional care will be
		needed for high availability.
	</li>
	<li>
		<strong>YugabyteDB</strong>: YugabyteDB is a distributed SQL database that is compatible
		with PostgreSQL. It can be used as a drop-in replacement for PostgreSQL in Hyvor Relay (we
		use YugabyteDB in Hyvor Relay Cloud).
	</li>
</ul>

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
		The PostgreSQL server is configured to allow connections from the app servers. We recommend
		fully disabling public access to the PostgreSQL server.
	</li>
	<li>Backup strategies are in place.</li>
</ul>

<h2 id="app-server">App Server</h2>

<h3 id="app-server-count">Number of App Servers</h3>

<p>
	The number of app servers you need depends on your expected email volume. Here are some rough
	guidelines, which are mostly on the safer side:
</p>

<Table columns="1fr 1fr">
	<TableRow head>
		<div>Server Count / Size</div>
		<div>Expected Email Volume</div>
	</TableRow>
	<TableRow>
		<div>1 server, 4GB RAM</div>
		<div>1,000,000 emails/day</div>
	</TableRow>
	<TableRow>
		<div>2 servers, 8GB RAM each</div>
		<div>10,000,000 emails/day</div>
	</TableRow>
</Table>

<p>
	Also, make sure to read the <a href="/hosting/scaling">Scaling</a> page on how to scale both PostgreSQL
	and email workers.
</p>

<h3 id="app-server-setup">App Server Setup</h3>

<p>
	We recommend using a Linux distribution like Ubuntu or Debian for the app servers. Our Cloud
	runs on Ubuntu 24.04 LTS. Each server should have <strong>Docker</strong> installed.
</p>
