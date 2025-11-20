<script>
	import { CodeBlock, Table, TableRow } from '@hyvor/design/components';
</script>

<h1>Prod Deploy</h1>

<p>
	This page covers a <strong>production-ready deployment</strong> that requires multiple servers.
	If you want to deploy Hyvor Relay for hobby or small to medium-sized projects (less than
	1,000,000 emails/day without high availability), refer to the
	<a href="/hosting/deploy-easy">Easy Deploy</a> page.
</p>

<h2 id="overview">Overview</h2>

<p>
	By the end of this guide, you will have a production-ready Hyvor Relay deployment with the
	following characteristics:
</p>

<ul>
	<li>
		<strong>Multiple app servers</strong>: One or more app servers running Hyvor Relay in a
		Docker Swarm cluster. Email sending is load balanced across the app servers. API, DNS, and
		incoming emails are also technically load balanced across the app servers, but a dedicated
		load balancer is required for highly traffic setups, which is discussed later (<a
			href="/hosting/scaling#haproxy">HAProxy</a
		>).
	</li>
	<li>
		<strong>Multiple IP Addresses</strong>: Each app server has one or more dedicated IP
		addresses for sending emails. Each IP address is assigned to a queue that sends
		transactional or distributional emails (user-dedicated queues are coming soon).
	</li>
	<li>
		<strong>Dedicated PostgreSQL server</strong>: A dedicated PostgreSQL server for the database
		and email queue.
	</li>
</ul>

<h2 id="pgsql">PostgreSQL</h2>

<p>
	Hyvor Relay uses PostgreSQL as the database and also as the message queue. Set up a PostgreSQL
	server in a production-ready manner. Hyvor Relay has been tested with PostgreSQL 18. If your
	cloud provider offers a managed PostgreSQL service, feel free to use it. It will make backups,
	failover, and scaling easier. Otherwise, set up a dedicated PostgreSQL server.
</p>

<p>
	Since setting up a PostgreSQL server depends a bit on how your infrastructure is set up, we will
	not go into details here. Whichever option you choose, make sure that:
</p>

<ul>
	<li>
		A dedicated database is created for Hyvor Relay. Recommended name: <code>hyvor_relay</code>.
		<CodeBlock code="CREATE DATABASE hyvor_relay;" />
	</li>
	<li>
		A dedicated user is created with all privileges on the Hyvor Relay database and a strong
		password.
		<CodeBlock
			code={`CREATE USER relay_servers WITH ENCRYPTED PASSWORD 'strong_password';
GRANT ALL PRIVILEGES ON DATABASE hyvor_relay TO relay_servers;`}
		/>
	</li>
	<li>
		Configured to allow connections from the app servers (ideally via a private network).
		<CodeBlock
			code={`# In pg_hba.conf
host    hyvor_relay    relay_servers xx.xx.xx.xx/yy    scram-sha-256`}
		/>
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
	See the <a href="/hosting/scaling">Scaling</a> page has more details on other factors that affect
	the number of servers you need.
</p>

<h3 id="ip-addresses">How many IP addresses?</h3>

<p>
	A <strong>queue</strong> is responsible for sending emails. By default, each project is assigned
	to the "transactional" or "distributional" queue based on the project type. Each queue has one
	or more IP addresses assigned to it. When an email is sent via a queue, one of the IP addresses
	assigned to that queue is used as the <strong>source IP address</strong>.
</p>

<p>
	Why does this matter? Because email providers track the reputation of IP addresses. If, for some
	reason, an IP address gets blacklisted, only the emails sent via that IP address are affected.
	You can easily remove that IP address from the queue and add a new one without affecting the
	other IP addresses.
</p>

<p>
	Therefore, we recommend having <strong>at least 2 IP addresses per queue</strong> (there are 2 default
	queues - "transactional" and "distributional") for production deployments.
</p>

<h3 id="server-requirements">Server Requirements</h3>

<ul>
	<li>
		<strong>Hardware</strong>: Each server with at least 4GB RAM and 2 vCPUs. More resources may
		be needed based on your expected email volume.
	</li>
	<li>
		<strong>Operating System</strong>: A Linux-based operating system. Hyvor Relay is tested on
		Ubuntu 24.04 LTS in production.
	</li>
	<li>
		<strong>Private Network</strong> (optional but recommended): A private network between the app
		servers and the PostgreSQL server. If available, you can use this to advertise Docker Swarm nodes
		and connect to the PostgreSQL server securely.
	</li>
</ul>

<h3 id="server-setup">Server Setup</h3>

<h4>1. Install Docker</h4>

<p>
	Follow the official Docker installation guide for your Linux distribution:
	<a target="_blank" href="https://docs.docker.com/engine/install/"
		>https://docs.docker.com/engine/install/</a
	>
</p>

<h4>2. Create a Docker Swarm</h4>

<p>On one of the app servers, run the following command to initialize the Docker Swarm cluster:</p>

<CodeBlock code="docker swarm init --advertise-addr <MANAGER-PRIVATE-IP-ADDRESS>" language={null} />

<p>
	You will see a command to join other nodes to the swarm. Run that command on the other app
	servers to add them to the swarm.
</p>

<CodeBlock
	code="docker swarm join --token <TOKEN> <MANAGER-PRIVATE-IP-ADDRESS>:2377"
	language={null}
/>

<p>
	Finally, run <code>docker node ls</code> on the manager node to verify that all nodes have joined
	the swarm.
</p>

<h4>3. .env Configuration</h4>

<p>
	First, SSH into the manager node and download the deployment files (<a
		href="https://github.com/hyvor/relay/tree/main/deploy"
		target="_blank">view on Github</a
	>).
</p>

<CodeBlock
	code={`
curl -LO https://github.com/hyvor/relay/releases/latest/download/deploy.tar.gz
tar -xzf deploy.tar.gz
cd deploy/prod
`}
/>

<p>
	<code>deploy/prod</code> directory contains two files:
</p>

<CodeBlock
	code={`
.env 			 	# Environment variables
compose.yaml			# Docker Compose file
`}
/>

<p>
	Edit the <code>.env</code> file to set the following variables:
</p>

<ul>
	<li>
		<code>APP_SECRET</code>: A strong random string. You can generate one using the following
		command:
		<CodeBlock code="openssl rand -base64 32" />
	</li>
	<li>
		<code>OIDC_ISSUER_URL</code>, <code>OIDC_CLIENT_ID</code>, <code>OIDC_CLIENT_SECRET</code>:
		Set these variables based on your OIDC provider configuration.
	</li>
	<li>
		<code>DATABASE_URL</code>: Set this to point to your PostgreSQL server.
	</li>
</ul>

<p>Then, run the following command to verify that the configuration is correct:</p>

<CodeBlock code="docker compose run --rm relay bin/console verify" />

<h4>4. Deploy Hyvor Relay</h4>

<!--  -->

<!-- 

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
