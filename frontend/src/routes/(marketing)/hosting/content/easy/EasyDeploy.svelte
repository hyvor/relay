<script>
	import { CodeBlock } from '@hyvor/design/components';
</script>

<h1>Easy Deploy</h1>

<p>
	This page covers how to deploy Hyvor Relay on a single server using Docker Compose. This is the
	easiest way to deploy Hyvor Relay, and it is suitable for testing Hyvor Relay or for small to
	medium-sized use cases.
</p>

<p>
	In general, on a server with 4GB RAM and 2 vCPUs, you should be able to easily send more than
	100,000 emails per day. However, if you need high availability and scalability, see the <a
		href="/hosting/deploy">Deploy</a
	> page, which uses multiple servers with Docker Swarm.
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
	<strong>IP Addresses</strong>: You need at least one static IPv4 address for the server. This IP
	will be assigned to the transactional queue.
</p>

<p>
	<strong>OS</strong>: A Linux-based OS is recommended for production use. Unless your preference
	or organization policy requires a different distribution, we recommend using Ubuntu 24.04 LTS,
	which is the same OS we use in our Cloud instance.
</p>

<p>
	<strong>Docker</strong>: Install the latest version, following the
	<a href="https://docs.docker.com/engine/install/" target="_blank"
		>official Docker installation guide</a
	>.
</p>

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
	You are now in the <code>deploy/easy</code> directory, which contains the Docker Compose files and
	other necessary files for this deployment.
</p>

<h3 id="env">2. Configure Environment Variables</h3>

<p>
	The <a href="https://github.com/hyvor/relay/blob/main/deploy/easy/.env" target="_blank"
		>.env file</a
	> contains the environment variables for the deployment. Open it in a text editor and set the following
	variables:
</p>

<h4 id="env-app-secret">App Secret</h4>

<p>
	The <code>APP_SECRET</code> variable is a 32-bytes key used to encrypt sensitive data (e.g., API
	keys, tokens) in the application. Use the following command to generate a base64-encoded key:
</p>

<CodeBlock
	code={`
openssl rand -base64 32
`}
/>

<h4 id="env-oidc">OIDC Configuration</h4>

<p>
	Hyvor Relay requires OIDC (OpenID Connect) for authentication. In your OIDC provider, create a
	new application and set the following values:
</p>

<CodeBlock
	code={`
OIDC_ISSUER_URL=https://your-oidc-provider.com
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
`}
/>

<p>You might also need to allow the following URLs in your OIDC provider:</p>

<ul>
	<li>
		<strong>Callback URL</strong>: <code>https://your-relay-domain.com/api/oidc/callback</code>
	</li>
	<li>
		<strong>Logout URL</strong>: <code>https://your-relay-domain.com</code>
	</li>
</ul>

<p>
	If needed, feel free to change other environment variables. See the <a href="/hosting/env"
		>Environment Variables</a
	> page for all available variables.
</p>

<h3 id="docker-compose">3. Start Docker Compose</h3>

<p>
	Once you have configured the environment variables, you can run the following command to start
	the services:
</p>

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
