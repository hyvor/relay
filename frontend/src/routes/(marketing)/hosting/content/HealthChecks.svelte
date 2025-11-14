<h1>Health Checks</h1>

<p>
	One of the unique features of Hyvor Relay is the built-in health checks. These checks make life
	much easier for the system administrators. A check is performed every hour and you can also run
	it manually at <strong>Sudo &rarr; Health &rarr; Run Checks</strong>.
</p>

<h2 id="checks-overview">Overview of Checks</h2>

<p>The following checks are performed:</p>

<ol>
	<li>
		<a href="#ptr"> All active IPs have valid PTR records </a>
	</li>
	<li>
		<a href="#ips-in-spf"> All IPs are included in SPF record </a>
	</li>
	<li>
		<a href="#queues-ip"> All queues have at least one IP </a>
	</li>
	<li>
		<a href="#instance-dkim"> Instance DKIM is correct</a>
	</li>
	<li>
		<a href="#no-unread-infra-bounces"> No unread infrastructure bounces </a>
	</li>
	<li>
		<a href="#blacklists"> None of the IPs are on known blacklists </a>
	</li>
</ol>

<h3 id="ptr">1. All active IPs have valid PTR records</h3>

<p>This checks ensures that for each sending IP address, there is</p>

<ul>
	<li>
		a valid forward A record that maps the domain name to the IP address. This is automatically
		handled by the
		<a href="/hosting/dns">DNS Server</a>.
		<div style="margin-top: 5px">
			(A) <code>smtpx.relay-instance.yourdomain.com</code> &rarr;
			<code>8.8.8.8</code>
		</div>
	</li>
	<li>
		a valid reverse PTR record that maps the IP address back to the domain name. PTR records are
		managed by the ISP or hosting provider that owns the IP address. We discuss this on the
		<a href="/hosting/setup#ptr">Setup</a> page.
		<div style="margin-top: 5px">
			(PTR) <code>8.8.8.8</code> &rarr; <code>smtpx.relay-instance.yourdomain.com</code>
		</div>
	</li>
</ul>

<p>
	If the check fails, it will list the IPs that do not have valid forward or reverse records and
	the associated error messages.
</p>

<h3 id="ips-in-spf">2. All IPs are included in SPF record</h3>

<p>
	This check ensures that all sending IP addresses are included in the SPF record of the instance
	domain. SPF records are managed on your <a href="/hosting/dns">DNS Server</a>.
</p>

<h3 id="queues-ip">3. All queues have at least one IP</h3>

<p>
	This check ensures that all queues have at least one sending IP address assigned. If a queue
	does not have any IPs, emails sent via that queue will not be delivered.
</p>

<h3 id="instance-dkim">4. Instance DKIM is correct</h3>

<p>
	This check ensures that the DKIM record for the instance domain is correctly set up. DKIM
	records are also managed on your DNS Server. You can view your Instnaec DKIM settings at
	<strong>Sudo &rarr; Setttings &rarr; Instance &rarr; Instance DKIM</strong>.
</p>

<h3 id="no-unread-infra-bounces">5. No unread infrastructure bounces</h3>

<p>
	When emails are sent, sometimes they bounce back due to various reasons (invalid email address,
	full inbox, etc.). Hyvor Relay automatically processes these bounces and categorizes them into
	<strong> infrastructure bounces </strong> and <strong>recipient bounces</strong> based on the enhanced
	SMTP status codes.
</p>

<p>
	<strong>Infrastructure bounces are nasty!</strong> They indicate issues with the sending
	infrastructure, such as IP blacklisting, DNS misconfigurations, etc. Hyvor Relay saves all those
	bounce emails for 30 days, which you can view at
	<strong>Sudo &rarr; Debug &rarr; Infrastructure Bounces</strong>.
</p>

<p>
	This check ensures that there are no unread infrastructure bounces. If there are any, it is
	strongly recommended to review them and take necessary actions to resolve the issues. Once you
	have reviewed the bounces, you can mark them as read to clear this check.
</p>

<h3 id="blacklists">6. None of the IPs are on known blacklists</h3>

<p>
	This check ensures that none of the sending IP addresses are listed on the following email
	blacklists:
</p>

<ul>
	<li>
		<a href="https://www.barracudacentral.org/" target="_blank" rel="nofollow noopener"
			>Barracuda</a
		>
	</li>
	<li>
		<a href="https://www.spamcop.net/" target="_blank" rel="nofollow noopener">SpamCop</a>
	</li>
	<li>
		<a href="https://mailspike.io/ip_verify" target="_blank" rel="nofollow noopener"
			>Mailspike</a
		>
	</li>
	<li>
		<a href="https://psbl.org/" target="_blank" rel="nofollow noopener">
			Passive Spam Block List (PSBL)
		</a>
	</li>
	<li>
		<a href="https://0spam.org/" target="_blank" rel="nofollow noopener">0Spam</a> (BL and RBL lists)
	</li>
</ul>

<p>
	If any IP addresses are found on these blacklists, the check will fail and list the affected IPs
	along with the respective blacklist names and a link to removal instructions.
</p>
