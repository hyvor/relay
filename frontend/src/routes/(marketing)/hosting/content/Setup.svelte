<script>
	import { Table, TableRow } from '@hyvor/design/components';
	import { DocsImage } from '@hyvor/design/marketing';
</script>

<h1>Setup</h1>

<p>
	Once Hyvor Relay is installed and you have access to Sudo, you can set up your email delivery
	configuration. This guide will help you configure your email delivery settings to ensure your
	emails are accepted by the recipient's mail servers and land in the inbox.
</p>

<ul>
	<li>
		<a href="#domain"> Primary Domain </a>
	</li>
	<li>
		<a href="#ptr-dns">PTR & DNS Records</a>
	</li>
	<li>
		<a href="#return-path">Return-Path & SPF</a>
	</li>
</ul>

<h2 id="domain">Primary Domain</h2>

<p>
	First, visit Sudo of your Hyvor Relay installation (<code>http://{'<server-ip>'}/sudo</code>). On
	the left sidebar, edit the primary domain.
</p>

<DocsImage src="/img/docs/setup-domain.png" alt="Primary Domain in Hyvor Relay Sudo" width={350} />

<p>
	You can use any domain you own, but we recommend using a subdomain of your main domain (e.g.,
	<code>relay.</code> or <code> hyvor-relay. </code>). The primary usage of this domain is to use
	for <a href="#ptr-dns">PTR records for IP addresses</a>. Note that sending emails is not
	restricted to this domain.
</p>

<p>
	Then, optionally, you can point that domain to your Hyvor Relay server's IP address using an <code
		>A</code
	> record. This is not required for email delivery, but it can ease Sudo and API access.
</p>

<p>
	<code>A record</code>: <code>relay.yourdomain.com</code> &rarr; <code>&lt;server-ip&gt;</code>
</p>

<h2 id="ptr-dns">PTR & DNS Records</h2>

<p>
	Each SMTP message has a <code>EHLO yourdomain.com</code> command, which identifies the sending server.
	In Hyvor Relay, each IP address uses a unique (sub)domain name for this purpose, which you can find
	in Sudo.
</p>

<DocsImage src="/img/docs/deliverability-ptr.png" alt="PTR & DNS Records in Hyvor Relay Sudo" />

<h3 id="ptr">PTR Record</h3>

<p>
	PTR, also known as reverse DNS, is a DNS record that maps an IP address to a domain name. Email
	servers use this record to verify the legitimacy of the sending server.
</p>

<p>
	To set up a PTR record, check the documentation of your IP address provider (cloud provider). Set
	the domain name to the one provided by Hyvor Relay for your IP address. Note that this domain is a
	subdomain of your <a href="#domain">Primary Domain</a>.
</p>

<p>Ex:</p>

<ul>
	<li>
		<code>8.8.8.8</code> &rarr; <code>smtp1.relay.yourdomain.com</code>
	</li>
	<li>
		<code>9.9.9.9</code> &rarr; <code>smtp2.relay.yourdomain.com</code>
	</li>
</ul>

<h3 id="dns">DNS Record</h3>

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
</Table>

<h2 id="return-path">Return-Path & SPF</h2>

<p>
	In a SMTP message, <code>MAIL FROM</code>, a.k.a <code>Return-Path</code>, is set to the email
	address where you want to receive bounces and other delivery notifications. The domain of this
	email address is also used for SPF verification, which is an important part of email
	deliverability.
</p>

<p>
	In Hyvor Relay, the Return-Path domain is your <a href="#domain">Primary Domain</a>.
</p>

<h3 id="spf">SPF</h3>

<p>
	SPF (Sender Policy Framework) is a DNS record that specifies which mail servers are allowed to
	send emails on behalf of a domain. The <code>MAIL FROM</code> (Return-Path) domain is used for the
	verification, not the <code>From</code> address domain of the email. Therefore, you need to only set
	up for your primary domain.
</p>
