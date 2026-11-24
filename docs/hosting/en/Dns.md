<script>
	import imgDnsCustom from '../images/dns-custom.png';
	import { DocsImage } from '@hyvor/design/marketing';
</script>

# DNS

Hyvor Relay comes with a DNS server that handles DNS queries for the <a
href="/hosting/setup#instance-domain">instance domain</a> and its subdomains. On each Hyvor Relay server, the DNS server listens on port 53 for UDP connections.

<ul>
<li>
<a href="#default-records">Default Records</a>
</li>
<li>
<a href="#custom-records">Custom Records</a>
</li>
</ul>

<h2 id="default-records">Default Records</h2>

The following DNS records are handled by default:

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

In addition to the default records, you can add custom DNS records in
**Sudo &rarr; Settings &rarr; DNS &rarr; Custom DNS Records**. You can add A, AAAA,
CNAME, MX, and TXT records for the instance domain and its subdomains here.

<DocsImage src={imgDnsCustom} alt="Custom DNS Records" />
