<script>
	import { Callout, Divider, Table, TableRow } from '@hyvor/design/components';
	import { DocsImage } from '@hyvor/design/marketing';
	import IconArrowLeftRight from '@hyvor/icons/IconArrowLeftRight';
	import IconLightbulb from '@hyvor/icons/IconLightbulb';
</script>

<h1>Setup</h1>

<p>
	Hyvor Relay is now installed on your server(s). The next step is to configure your DNS to access
	Hyvor Relay and ensure optimal email deliverability.
</p>

<ul style="list-style-type: none;">
	<li>
		<a href="#web-domain">(1) Web Domain </a>
	</li>
	<li>
		<a href="#sudo">(2) Access Sudo</a>
	</li>
	<li>
		<a href="#instance-domain">(3) Instance Domain </a>
	</li>
	<li>
		<a href="#ptr">(4) PTR Records</a>
	</li>
	<li>
		<a href="#health-checks">(5) Health Checks</a>
	</li>
</ul>

<h2 id="web-domain">(1) Web Domain</h2>

<p>
	Web domain is where you access the Hyvor Relay Console, Sudo, and API. This is the domain name
	of the environment variable <code>WEB_URL</code>
	you set during installation. Point the web domain to one of your server's IP addresses using an
	<code>A</code> record.
</p>

<Table columns="1fr 3fr 3fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>Value</div>
	</TableRow>
	<TableRow>
		<div><code>A</code></div>
		<div><code>relay.yourdomain.com</code></div>
		<div><code>x.x.x.x</code> (your server IP)</div>
	</TableRow>
</Table>

<p>
	If you used <strong>https</strong> in the <code>WEB_URL</code>, Caddy will automatically obtain
	and configure a TLS certificate for your web domain using Let's Encrypt. This might take a few
	minutes to complete. Make sure to monitor logs.
</p>

<p>
	Once the DNS changes have propagated, you should see the Hyvor Relay homepage at
	<strong>https://relay.yourdomain.com</strong>.
</p>

<Callout type="info">
	{#snippet icon()}
		💡
	{/snippet}
	On Hyvor Relay Cloud, the web domain is <strong>relay.hyvor.com</strong>.
</Callout>

<h2 id="sudo">(2) Access Sudo</h2>

<p>
	Next, visit <code>https://relay.yourdomain.com/sudo</code> to access Sudo, the administration panel
	of Hyvor Relay. Log in using your OIDC credentials.
</p>

<Callout type="warning">
	{#snippet icon()}
		⚠️
	{/snippet}
	For fresh installations, the first user who logs in with OIDC credentials becomes a sudo user.
</Callout>

<p>
	In <strong>Sudo &rarr; Servers</strong>, you should see your server(s) listed along with their
	public IP addresses.
</p>

<h2 id="instance-domain">(3) Instance Domain</h2>

<p>
	You configured the instance domain during the installation using the environment variable
	<code>INSTANCE_DOMAIN</code>. Example: <strong>mail.relay.yourdomain.com</strong>.
</p>

<p>The instance domain and its subdomains are used for many things:</p>

<ul>
	<li>
		To populate <code>PTR</code> records for outgoing IP addresses.
	</li>
	<li>For instance DKIM signing (useful for feedback loops).</li>
	<li>
		For the <code>MX</code> records of the incoming mail server that is repsonsible for bounces,
		complaints, and <a href="/docs/send-emails-smtp">sending via SMTP</a>.
	</li>
</ul>

<p>
	The instance domain is critical for email deliverability. Hyvor Relay is designed to manage the
	DNS records of the instance domain and its subdomains, making management easier.
</p>

<p>
	To delegate DNS management of the instance domain to Hyvor Relay, set up a
	<code>NS</code> record pointing to Hyvor Relay's DNS server.
</p>

<Table columns="1fr 3fr 3fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>Value</div>
	</TableRow>
	<TableRow>
		<div><code>NS</code></div>
		<div>
			<code>mail.relay.yourdomain.com</code> <br />(your instance domain)
		</div>
		<div><code>ns.relay.yourdomain.com</code></div>
	</TableRow>
	<TableRow>
		<div><code>A</code></div>
		<div><code>ns.relay.yourdomain.com</code></div>
		<div><code>x.x.x.x</code> (your server IP)</div>
	</TableRow>
</Table>

<p>
	This tells the world that the DNS records of <code>mail.relay.yourdomain.com</code> and its
	subdomains are managed by <code>ns.relay.yourdomain.com</code>, which points to your Hyvor Relay
	instance's IP address.
</p>

<Callout type="info">
	{#snippet title()}
		Redundancy
	{/snippet}
	{#snippet icon()}
		<IconArrowLeftRight />
	{/snippet}
	If you run multiple Hyvor Relay servers, it is recommended to set up multiple NS records pointing
	to different servers for redundancy. (Ex: <strong>ns1.relay.yourdomain.com</strong>
	pointing to one server IP and
	<strong>ns2.relay.yourdomain.com</strong> pointing to another server IP.)
</Callout>

<Callout type="info" style="margin-top: 15px;">
	{#snippet icon()}
		💡
	{/snippet}
	On Hyvor Relay Cloud, the instance domain is <strong>mail.hyvor-relay.com</strong>.
</Callout>

<h2 id="ptr">(3) PTR Records</h2>

<p>
	PTR, also known as reverse DNS, is a DNS record that maps an IP address to a domain name. SMTP
	messages contain a
	<code>EHLO {'<domain>'}</code> command, which identifies the sending server (or IP address). In
	Hyvor Relay, each sending IP address uses a unique subdomain of the
	<a href="#instance-domain">instance domain</a> as the domain name.
</p>

<p>You can find the domain name of each IP address in Sudo &rarr; Servers section.</p>

<DocsImage src="/img/docs/setup-ptr.png" alt="PTR & DNS Records in Hyvor Relay Sudo" />

<p>
	Most email providers require the sending IP address to have a PTR record that points to the
	domain name (<strong>"reverse DNS match"</strong>) and the domain name to have an A record that
	points to the IP address (<strong>"forward DNS match"</strong>).
</p>

<p>
	Setting PTR records is something Hyvor Relay's DNS server cannot do for you, as it requires
	access to the IP address's reverse DNS zone, which is managed by your hosting provider. Consult
	your hosting provider's documentation or support and set up PTR records for <strong>ALL</strong>
	IP addresses as shown in Sudo.
</p>

<p>Ex:</p>

<ul>
	<li>
		<code>8.8.8.8</code> &rarr; <code>smtp1.mail.relay.yourdomain.com</code>
	</li>
	<li>
		<code>9.9.9.9</code> &rarr; <code>smtp2.mail.relay.yourdomain.com</code>
	</li>
</ul>

<h2 id="health-checks">(4) Health Checks</h2>

<p>
	After the above steps are completed, run a full health check at <strong
		>Sudo &rarr; Health &rarr; Run Checks</strong
	> to verify that everything is set up correctly. Note that some DNS-related checks might take longer
	to pass due to DNS caching.
</p>

<p>If everthing is passing, your Hyvor Relay instance is ready to send emails!</p>

<Divider color="var(--gray-light)" margin={30} />

<h2 id="whats-next">What's Next?</h2>

<ul>
	<li>
		Visit the <strong>Console</strong> (<code>/console</code>),
		<a href="/docs#project">create a project</a>, and
		<a href="/docs/send-emails">send emails</a>.
	</li>
	<li>
		<a href="/hosting/monitoring">Set up monitoring</a> to get alerts on issues.
	</li>
	<li>
		See <a href="/hosting/scaling">Scaling</a> to learn how to scale Hyvor Relay.
	</li>
</ul>

<Divider color="var(--gray-light)" margin={30} />

<h2 id="sudo-users">Managing Sudo Users</h2>

<p>You can add and remove sudo users from the command line.</p>

<ul>
	<li>SSH into one of the servers.</li>
	<li>
		<code>cd</code> into the Hyvor Relay deployment directory.
	</li>
	<li>
		<code> docker compose exec -it relay bash </code> to enter the app container.
	</li>
	<li>
		Then, use the following commands:
		<ul style="margin-top: 8px">
			<li>
				<code>bin/console sudo:list</code>: List all sudo users.
			</li>
			<li>
				<code>bin/console sudo:add {'<email>'}</code>: Add a new sudo user by email.
			</li>
			<li>
				<code>bin/console sudo:remove {'<id>'}</code>: Remove a sudo user by ID.
			</li>
		</ul>
	</li>
</ul>
