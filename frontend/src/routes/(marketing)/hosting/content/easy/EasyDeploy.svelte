<script>
	import { CodeBlock, Divider } from '@hyvor/design/components';
</script>

<h1>Easy Deploy</h1>

<p>
	This page covers how to deploy Hyvor Relay on a single server using Docker Compose. This is the
	easiest way to deploy Hyvor Relay, and it is suitable for <strong>most use cases</strong> where you
	need a reliable transactional email service.
</p>

<p>
	In general, on a server with 4GB RAM and 2 vCPUs, you should be able to easily send more than
	<a href="/hosting/scaling#onemil-per-day"> 1,000,000 emails per day</a>. However, if you need
	high availability and scalability, see the <a href="/hosting/deploy">Prod Deploy</a> page, which
	uses multiple servers with Docker Swarm.
</p>

<ul>
	<li>
		<a href="#prerequisites">Prerequisites</a>
	</li>
	<li>
		<a href="#install">Install</a>
	</li>
	<li><a href="#setup">Setup</a></li>
</ul>

<h2 id="prerequisites">Prerequisites</h2>

<p>
	<strong>Server</strong>: A server with at least 1GB RAM and 1 vCPU. 2GB RAM and 2 vCPU are
	recommended for production use.
</p>

<p>
	<strong>IP Addresses</strong>: You need at least one static IPv4 address for the server. Add
	more IP addresses for more queues.
</p>

<p>
	<strong>OS</strong>: A Linux-based operating system. Hyvor Relay is tested on Ubuntu 24.04 LTS
	in production.
</p>

<p>
	<strong>Docker</strong>: Install the latest version, following the
	<a href="https://docs.docker.com/engine/install/" target="_blank">official guide</a>.
</p>

<p>
	<strong> OpenID Connect (OIDC) Provider </strong>: Hyvor Relay relies on OIDC for
	authentication. Create an application in your OIDC provider and obtain the issuer URL, client
	ID, and client secret. Then, allow the following URLs:
</p>

<ul>
	<li>
		<strong>Callback URL</strong>: <code>http://your-server-ip/api/oidc/callback</code>
	</li>
	<li>
		<strong>Logout URL</strong>: <code>http://your-server-ip</code>
	</li>
</ul>

<p>
	<strong>Firewall</strong>: the following ports should be open on your server:
</p>

<ul>
	<li>
		<strong>443</strong> and <strong>80</strong>: API
	</li>
	<li>
		<strong>25</strong>: SMTP server for incoming emails (bounces & complaints)
	</li>
	<li>
		<strong>587</strong>: SMTP server for sending emails via SMTP
	</li>
	<li>
		<strong>53</strong>: DNS Server
	</li>
</ul>

<h2 id="install">Install</h2>

<h3 id="download-tarball">1. Download Deployment Files</h3>

<p>
	First, download the latest deployment files (<a
		href="https://github.com/hyvor/relay/tree/main/deploy"
		target="_blank">view on Github</a
	>).
</p>

<CodeBlock
	code={`
curl -LO https://github.com/hyvor/relay/releases/latest/download/deploy.tar.gz
tar -xzf deploy.tar.gz
cd deploy/easy
`}
/>

<p>
	<code>deploy/easy</code> directory contains the following files:
</p>

<CodeBlock
	code={`
.env 			 	# Environment variables
compose.yaml			# Docker Compose file
`}
/>

<h3 id="env">2. Configure Environment Variables</h3>

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
		<code>POSTGRES_PASSWORD</code>: Use a strong, URL-safe password for the Postgres database.
		You can generate one using the following command:
		<CodeBlock code="openssl rand -base64 32 | tr '+/' '-_' | tr -d '='" />
	</li>
	<li>
		<code>WEB_URL</code>: The public URL where Hyvor Relay and its API will be accessible.
		Example:
		<code>https://relay.yourdomain.com</code>. This is required for TLS. If you run Hyvor Relay
		behind a reverse proxy that handles TLS, use <code>http://</code>.
	</li>
	<li>
		<code>INSTANCE_DOMAIN</code>: The dedicated domain name used for the incoming mail server,
		EHLO identification, and PTR records. Example: <code>mail.relay.yourdomain.com</code>.
		<strong>Must be different from the Web URL</strong>. DNS of this (sub)domain will be handled
		by Hyvor Relay.
	</li>
	<li>
		<code>OIDC_ISSUER_URL</code>, <code>OIDC_CLIENT_ID</code>, <code>OIDC_CLIENT_SECRET</code>:
		Set these variables based on your OIDC provider configuration.
	</li>
</ul>

<p>
	See the <a href="/hosting/env">Environment Variables</a> page for all available variables.
</p>

<h3 id="docker-compose">3. Start Docker Compose</h3>

<p>Start the services:</p>

<CodeBlock
	code={`
docker compose up -d
`}
/>

<p>
	This command will start the Hyvor Relay services in detached mode. You can check the logs using:
</p>

<CodeBlock
	code={`
docker compose logs -f relay
`}
/>

<p>
	You should see the logs indicating that the application has run migrations, configured the
	server and the IP addresses, and started the application (email workers, webhook workers, etc.).
</p>

<p>You can run the following command for a quick status check:</p>

<CodeBlock
	code={`
docker compose exec relay bin/console verify
`}
/>

<h2 id="setup">Setup</h2>

<p>
	Next, head to the <a href="/hosting/setup">Setup</a> page to continue the setup process.
</p>

<!-- <p>
	Once the application is running, you should see the Hyvor Relay homepage at <strong
		>https://relay.yourdomain.com</strong
	>.
</p> -->

<Divider color="var(--gray-light)" margin={30} />

<h2 id="things-to-know">Things to know</h2>

<h3 id="app-secret">App Secret</h3>

<p>
	The <code>APP_SECRET</code> variable is a 32-bytes key used to encrypt sensitive data (e.g., API
	keys, tokens) in the application. You should not change this value after the initial setup, as
	it will invalidate existing encrypted data. Key rotation is not supported yet, but
	<a href="https://github.com/hyvor/internal/issues/55" target="_blank">planned</a>.
</p>

<h3 id="host-network">Host Network</h3>

<p>
	The application uses the <a
		href="https://docs.docker.com/engine/network/drivers/host/"
		target="_blank">host network mode</a
	> to bind to the server's IP addresses directly. This allows Hyvor Relay to control the IP addresses
	used for sending emails. Other network modes (e.g., bridge, overlay) are not supported.
</p>

<h3 id="external-postgres">External Postgres</h3>

<p>
	If you want to use an external Postgres database (for example, a managed database service), you
	can do so by updating the <code>DATABASE_URL</code> environment variable in the
	<code>.env</code> file.
</p>
