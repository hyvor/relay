<script>
	import { CodeBlock } from '@hyvor/design/components';
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
	<strong>OS</strong>: A Linux-based OS is recommended for production use. Hyvor Relay is tested
	on Ubuntu 24.04 LTS.
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

<h2 id="before-start">Before you start...</h2>

<ul>
	<li>
		<strong>Firewall</strong>: the following ports should be open on your server:
		<ul>
			<li>
				<strong>80</strong>: API
			</li>
			<li>
				<strong>25</strong>: SMTP server for incoming emails (bounces & complaints)
			</li>
			<li>
				<strong>53</strong>: DNS Server
			</li>
		</ul>
	</li>
	<li>
		<strong>Host Network</strong>: The application runs in the
		<a href="https://docs.docker.com/engine/network/drivers/host/">host network mode</a> to use the
		server's IP addresses directly. Hyvor Relay does not support other network modes (e.g., bridge,
		overlay).
	</li>
	<li>
		<strong>Postgres</strong>: The Docker Compose file includes a <code>postgres:18</code>
		service, which is suitable for most use cases. However, if you want to use an external Postgres
		database (for example, a managed database service), you can do so by updating the
		<code>DATABASE_URL</code> environment variable in the <code>.env</code> file.
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
config			 	# Bash script to update .env file
`}
/>

<h3 id="env">2. Configure Environment Variables</h3>

<p>Run the config script:</p>

<CodeBlock
	code={`
./config
`}
/>

<p>This script does the following, in order:</p>

<ul>
	<li>
		Set <code>APP_SECRET</code> to a secure random value in <code>.env</code> file, generated
		using
		<code>openssl rand -base64 32</code>.
	</li>
	<li>
		Generate a strong random password for the Postgres database and update the
		<code>DATABASE_URL</code> variable in <code>.env</code> file and the corresponding variable
		in <code>compose.yaml</code>.
	</li>
	<li>
		Prompt you to enter the OIDC provider details (issuer URL, client ID, client secret) and
		update the corresponding variables in <code>.env</code> file.
	</li>
</ul>

<p>Make sure to verify the config:</p>

<CodeBlock
	code={`
cat .env
cat compose.yaml
`}
/>

<p>
	If needed, feel free to change other environment variables. See the
	<a href="/hosting/env">Environment Variables</a> page for all available variables.
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
docker compose logs -f app
`}
/>

<p>
	You should see the logs indicating that the application has run migrations, configured the
	server and the IP addresses, and started the application (email workers, webhook workers, etc.).
</p>

<h2 id="setup">Setup</h2>

<p>
	Once the application is running, you should see the Hyvor Relay homepage at <strong
		>http://your-server-ip</strong
	>.
</p>

<p>
	Next, head to the <a href="/hosting/setup">Setup</a> page to learn how to set up your Hyvor Relay
	instance for best deliverability.
</p>

<hr />

<h2 id="things-to-know">Things to know</h2>

<h3 id="app-secret">App Secret</h3>

<p>
	The <code>APP_SECRET</code> variable is a 32-bytes key used to encrypt sensitive data (e.g., API
	keys, tokens) in the application. You should not change this value after the initial setup, as
	it will invalidate existing encrypted data. Key rotation is not supported yet, but
	<a href="https://github.com/hyvor/internal/issues/55" target="_blank">planned</a>.
</p>
