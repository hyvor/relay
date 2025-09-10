<script>
	import { DocsImage } from '@hyvor/design/marketing';
</script>

<h1>DNS</h1>

<p>
	Hyvor Relay comes with a DNS server that handles DNS queries for the <a
		href="/hosting/setup#instance-domain">instance domain</a
	> and its subdomains.
</p>

<ul>
	<li>
		<a href="#default-records">Default Records</a>
	</li>
	<li>
		<a href="#custom-records">Custom Records</a>
	</li>
</ul>

<h2 id="default-records">Default Records</h2>

<p>The following DNS records are handled by default:</p>

<ul>
	<li>
		Forward A records for each IP address <br />
		<code>smtp1.{'<instance-domain>'}</code>
		&rarr; <code>x.x.x.x</code>
	</li>
	<li>
		MX record for the instance domain <br />
		<code>{'<instance-domain>'}</code> &rarr;
		<code>mx.{'<instance-domain>'}</code>
	</li>
	<li>
		A records for the MX domain <br />
		<code>mx.{'<instance-domain>'}</code> &rarr;
		<code>x.x.x.x</code>
	</li>
	<li>
		SPF (TXT) record for the instance domain <br />
		<code>{'<instance-domain>'}</code> &rarr;
		<code>v=spf1 ...</code>
	</li>
	<li>
		DKIM (TXT) record for the instance domain <br />
		<code>default._domainkey.{'<instance-domain>'}</code> &rarr;
		<code>v=DKIM1 ...</code>
	</li>
</ul>

<h2 id="custom-records">Custom Records</h2>

<p>
	In addition to the default records, you can add custom DNS records in
	<strong>Sudo &rarr; Settings &rarr; DNS &rarr; Custom DNS Records</strong>. You can add A, AAAA,
	CNAME, MX, and TXT records for the instance domain and its subdomains here.
</p>

<DocsImage src="/img/docs/dns-custom.png" alt="Custom DNS Records" />
