<h1>Easy Deploy</h1>

<p>
	This page covers how to deploy Hyvor Relay on a single server using Docker Compose. This is the
	easiest way to deploy Hyvor Relay, and it is suitable for small to medium-level email sending.
</p>

<p>
	In general, on a server with 4GB RAM and 2 vCPUs, you should be able to easily send more than
	100,000 emails per day. However, if you need high availability and scalability, see the <a
		href="/hosting/deploy">Deploy</a
	> page, which uses multiple servers with Docker Swarm.
</p>

<h2 id="prerequisites">Prerequisites</h2>

<p>
	<strong>Server</strong>: A server with at least 1GB RAM and 1 vCPU. 2GB RAM and 2 vCPU are
	recommended for production use.
</p>

<p>
	<strong>IP Addresses</strong>: You need at least one static IP address for the server. This IP
	will be assigned to the transactional queue.
</p>

<p>
	<strong>OS</strong>: A Linux-based OS (Ubuntu, Debian, etc.) is recommended for production use.
	Unless your preference or organization policy requires a different distribution, we recommend
	using Ubuntu 24.04 LTS, which is the same OS we use in our Cloud instance.
</p>

<p>
	<strong>Docker</strong>: Install the latest version, following the
	<a href="https://docs.docker.com/engine/install/" target="_blank"
		>official Docker installation guide</a
	>.
</p>

<h2 id="compose-file">Compose File</h2>

<p></p>
