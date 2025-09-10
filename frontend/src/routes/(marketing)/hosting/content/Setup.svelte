<script>
	import { Callout, Divider, Table, TableRow } from '@hyvor/design/components';
	import { DocsImage } from '@hyvor/design/marketing';
	import IconArrowLeftRight from '@hyvor/icons/IconArrowLeftRight';
	import IconInfoCircle from '@hyvor/icons/IconInfoCircle';
	import IconLightbulb from '@hyvor/icons/IconLightbulb';
</script>

<h1>Setup</h1>

<p>
	Once Hyvor Relay is installed, visit <code>http://{'<server-ip>'}/sudo</code> to access Sudo, the
	administration panel of Hyvor Relay. For fresh installations, the first user who logs in with OIDC
	credentials becomes a sudo user.
</p>

<p>Let's configure your instance for best email deliverability:</p>

<ul style="list-style-type: none;">
	<li>
		<a href="#web-domain">(1) Web Domain </a>
	</li>
	<li>
		<a href="#instance-domain">(2) Instance Domain </a>
	</li>
	<li>
		<a href="#ptr">(3) PTR Records</a>
	</li>
</ul>

<h2 id="web-domain">(1) Web Domain</h2>

<p>
	Web domain is where you access the Hyvor Relay Console, Sudo, and API. Point the web domain to
	one of your server's IP addresses using an <code>A</code> record.
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

<Callout type="info">
	{#snippet icon()}
		💡
	{/snippet}
	On Hyvor Relay Cloud, the web domain is <strong>relay.hyvor.com</strong>.
</Callout>

<h2 id="instance-domain">(2) Instance Domain</h2>

<p>
	The instance domain and its subdomains are used for the
	<code>EHLO</code> domain in SMTP and PTR records, and it is crucial for email deliverability.
</p>

<p>You can either:</p>

<ul>
	<li>
		Use a subdomain (ex: <strong>relay-instance.yourdomain.com</strong>) of your main domain.
		This must be different from the web domain.
	</li>
	<li>
		Or, you can use a completely different domain (ex: <strong>yourdomain-relay.com</strong>).
		If you allow third-party users to send emails using your Hyvor Relay installation, this is
		preferable to avoid any email reputation issues with your main domain.
	</li>
</ul>

<Callout type="info">
	{#snippet icon()}
		💡
	{/snippet}
	On Hyvor Relay Cloud, the instance domain is <strong>hyvor-relay.com</strong>.
</Callout>

<p>
	To set up the instance domain, visit Sudo (<code>http://{'<web-domain>'}/sudo</code>) and edit
	the instance domain.
</p>

<DocsImage src="/img/docs/setup-domain.png" alt="Instance Domain in Hyvor Relay Sudo" width={350} />

<p>
	Email deliverability requires several DNS records that needs to be synced with the instance.
	Manully changing these records can be tedious. To solve this problem, Hyvor Relay provides an
	in-built DNS server that can manage all the DNS records of the instance domain. To use that, set
	up an <code>NS</code> record as follows:
</p>

<Table columns="1fr 3fr 3fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>Value</div>
	</TableRow>
	<TableRow>
		<div><code>NS</code></div>
		<div><code>relay-instance.yourdomain.com</code></div>
		<div><code>relay-ns.yourdomain.com</code></div>
	</TableRow>
	<TableRow>
		<div><code>A</code></div>
		<div><code>relay-ns.yourdomain.com</code></div>
		<div><code>x.x.x.x</code> (your server IP)</div>
	</TableRow>
</Table>

<p>
	This tells the world that the DNS records of <code>relay-instance.yourdomain.com</code> and its subdomains
	are managed by Hyvor Relay DNS servers.
</p>

<Callout type="info">
	{#snippet title()}
		Redundancy
	{/snippet}
	{#snippet icon()}
		<IconArrowLeftRight />
	{/snippet}
	If you run multiple Hyvor Relay servers, it is recommended to set up multiple NS records pointing
	to different servers for redundancy. (Ex: <strong>relay-ns1.yourdomain.com</strong>
	pointing to one server IP and
	<strong>relay-ns2.yourdomain.com</strong> pointing to another server IP.)
</Callout>

<br />

<Callout type="info">
	{#snippet title()}
		Can't use NS records?
	{/snippet}
	{#snippet icon()}
		<IconInfoCircle />
	{/snippet}
	If you cannot use NS records due to domain registrar limitations, organizational policies, or other
	reasons, you can
	<a href="/hosting/dns">manually set up the required DNS records</a>.
</Callout>

<h2 id="ptr">(3) PTR Records</h2>

<p>
	PTR, also known as reverse DNS, is a DNS record that maps an IP address to a domain name. SMTP
	messages contains a
	<code>EHLO {'<domain>'}</code> command, which identifies the sending server (or IP address). In
	Hyvor Relay, each sending IP address uses a unique (sub)domain of the
	<a href="#instance-domain">instance domain</a> as the domain name.
</p>

<p>You can find the domain name of each IP address in Sudo &rarr; Servers section.</p>

<DocsImage src="/img/docs/setup-ptr.png" alt="PTR & DNS Records in Hyvor Relay Sudo" />

<p>
	Most email providers require the sending IP address to have a PTR record that points to the
	domain name (<strong>"reverse DNS match"</strong>) and the domain name to have an A record that
	points to the IP address (<strong>"forward DNS match"</strong>).
</p>

<h3 id="add-ptr">Adding PTR Records</h3>

<p>
	Setting PTR records is something Hyvor Relay's DNS server cannot do for you, as it requires
	access to the IP address's reverse DNS zone, which is usually managed by your hosting provider.
	Consult your hosting provider's documentation or support and set up PTR records for <strong
		>ALL</strong
	>
	IP addresses as shown in Sudo.
</p>

<p>Ex:</p>

<ul>
	<li>
		<code>8.8.8.8</code> &rarr; <code>smtp1.relay-instance.yourdomain.com</code>
	</li>
	<li>
		<code>9.9.9.9</code> &rarr; <code>smtp2.relay-instance.yourdomain.com</code>
	</li>
</ul>

<Callout type="info">
	{#snippet icon()}
		<IconLightbulb />
	{/snippet}
	There is a health check to verify PTR records. Visit Sudo &rarr; Health section to see the results.
</Callout>

<Divider color="var(--gray-light)" margin={30} />

<h2 id="sudo-users">Managing Sudo Users</h2>

<p>You can add and remove sudo users from the command line.</p>

<ul>
	<li>SSH into one of the servers.</li>
	<li>
		<code>cd</code> into the Hyvor Relay deployment directory.
	</li>
	<li>
		<code> docker compose exec -it app sh </code> to enter the app container.
	</li>
	<li>
		Then, use the following commands:
		<ul style="margin-top: 8px">
			<li>
				<code>sudo:list</code>: List all sudo users.
			</li>
			<li>
				<code>sudo:add {'<email>'}</code>: Add a new sudo user by email.
			</li>
			<li>
				<code>sudo:remove {'<id>'}</code>: Remove a sudo user by ID.
			</li>
		</ul>
	</li>
</ul>

<h2 id="whats-next">What's Next?</h2>

<ul>
	<li>
		Visit <strong>Sudo &rarr; Health</strong> and make sure all checks are passing.
	</li>
	<li>
		<a href="/hosting/monitoring">Set up monitoring</a> to get alerts on issues.
	</li>
	<li>
		See <a href="/hosting/scaling">Scaling & High Availability</a> to learn how to scale Hyvor Relay.
	</li>
</ul>

<!--  -->

<!-- <h3 id="dns">DNS Record</h3>

<p>
	SMTP servers now know the domain name of the sending IP address. However, most email providers
	will also check the DNS records of that domain to verify its legitimacy. To pass this check, for
	each IP address, point its designated domain name to the IP address using an <code>A</code> record.
</p>

<p>Ex:</p>

<Table columns="1fr 2fr 1fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>IP Address</div>
	</TableRow>
	<TableRow>
		<div>A</div>
		<div>smtp1.relay.yourdomain.com</div>
		<div><code>8.8.8.8</code></div>
	</TableRow>
	<TableRow>
		<div>A</div>
		<div>smtp2.relay.yourdomain.com</div>
		<div><code>9.9.9.9</code></div>
	</TableRow>
	<TableRow>
		<div>...</div>
		<div>...</div>
		<div>...</div>
	</TableRow>
</Table> -->

<!-- <h2 id="return-path">(3) Return-Path (SPF & MX)</h2>

<p>
	In a SMTP message, <code>MAIL FROM</code>, a.k.a <code>Return-Path</code>, is set to the email
	address where you want to receive bounces and other delivery notifications. The domain of this
	email address is also used for SPF verification, which is an important part of email
	deliverability.
</p>

<p>
	In Hyvor Relay, the Return-Path domain is your <a href="#domain">Instance Domain</a>.
</p>

<h3 id="spf">SPF</h3>

<p>
	SPF (Sender Policy Framework) is a DNS record that specifies which mail servers are allowed to
	send emails on behalf of a domain. The <code>MAIL FROM</code> (Return-Path) domain is used for
	the verification, not the <code>From</code> address domain of the email. Therefore, you need to only
	set up for your Instance Domain.
</p>

<p>Example SPF record:</p>

<Table columns="1fr 2fr 2fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>Value</div>
	</TableRow>
	<TableRow>
		<div>TXT</div>
		<div>relay.yourdomain.com</div>
		<div><code>v=spf1 ip4:8.8.8.8 -all</code></div>
	</TableRow>
</Table>

<p>You should add all sending IP addresses of your Hyvor Relay installation to the SPF record.</p>

<ul>
	<li>
		Add all IPs one by one:
		<code>v=spf1 ip4:1.1.1.1 ip4:2.2.2.2 -all</code>

		<p>
			If you have many IP addresses, this can be tedious. You can copy the full value of the
			SPF record from the Sudo &rarr; Health section.
		</p>
	</li>
	<li>
		Add IP ranges:
		<code>v=spf1 ip4:1.1.1.0/24 -all</code>

		<p>
			If all your IP addresses are in a range, you can use CIDR notation to specify the range.
			Make sure you control all the IP addresses in that range to avoid spoofing.
		</p>
	</li>
</ul>

<p>SPF Breakdown:</p>

<ul>
	<li>
		<code>v=spf1</code>: Indicates that the TXT record is an SPF record.
	</li>
	<li>
		<code>ip4:{'<ip>'}</code>: Allow the specified IPv4 address or range to send emails for this
		domain.
	</li>
	<li>
		<code>-all</code>: Indicates that all other IP addresses are not allowed to send emails for
		this domain. This is a strict policy. You can use <code>~all</code> (with tilde) for a soft policy,
		which allows other IPs but marks them as suspicious.
	</li>
</ul>

<h2 id="mx">MX</h2>

<p>
	When you send a SMTP message, sometimes, the recipient's mail server will accept the email but
	later fail to deliver it to the recipient's mailbox in cases like the mailbox being full. Such
	cases cannot be known by the sender just by looking at the SMTP response. The standard way that
	email providers handle such cases is to send a bounce email to the <code>Return-Path</code> address.
</p>

<p>
	First, in sudo enable "Incoming" setting for at least one of your IP addresses. This will start
	a process that listens to port 25 of the IP address and accepts incoming emails. In production
	systems, we recommend enabling <strong>one IP per server</strong> and having at least two servers
	for redundancy.
</p>

<DocsImage src="/img/docs/setup-incoming.png" alt="Incoming Setting in Hyvor Relay Sudo" />

<p>
	Then, create an MX record for your <a href="#domain">Instance Domain</a>. Here we chose
	<code>mx.</code> subdomain.
</p>

<Table columns="1fr 2fr 2fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>Value</div>
	</TableRow>
	<TableRow>
		<div>MX</div>
		<div><code>relay.yourdomain.com</code></div>
		<div><code>mx.relay.yourdomain.com</code></div>
	</TableRow>
</Table>

<p>
	Then, set up one <code>A</code> record for each IP address that you have enabled for incoming emails.
</p>

<Table columns="1fr 2fr 1fr">
	<TableRow head>
		<div>Type</div>
		<div>Host</div>
		<div>IP Address</div>
	</TableRow>
	<TableRow>
		<div>A</div>
		<div><code>mx.relay.yourdomain.com</code></div>
		<div><code>1.1.1.1</code></div>
	</TableRow>
	<TableRow>
		<div>A</div>
		<div><code>mx.relay.yourdomain.com</code></div>
		<div><code>2.2.2.2</code></div>
	</TableRow>
	<TableRow>
		<div>...</div>
		<div>...</div>
		<div>...</div>
	</TableRow>
</Table> -->
