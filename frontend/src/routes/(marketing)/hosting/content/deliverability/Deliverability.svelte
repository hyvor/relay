<script>
	import { Callout, Tag } from '@hyvor/design/components';
	import RelayHandledTag from './RelayHandledTag.svelte';
</script>

<h1>Deliverability</h1>

<p>
	<strong>Deliverability</strong> refers to the ability of an email to successfully reach the recipient's
	inbox without being blocked or marked as spam.
</p>

<p>
	Early email systems had minimal filtering. Starting from the <a
		href="https://en.wikipedia.org/wiki/Laurence_Canter_and_Martha_Siegel"
		target="_blank"
	>
		green card spam incident</a
	> in the 90s, email providers began implementing spam filters to protect users from unwanted emails.
	Nowadays, email deliverability is a complex field involving various techniques and best practices
	to ensure that legitimate emails reach their intended recipients.
</p>

<p>
	This guide will help you understand how to improve the deliverability of emails sent via Hyvor
	Relay.
</p>

<p>Jump to each section:</p>

<ul>
	<li>
		<a href="#content">Content</a>
	</li>
	<li>
		<a href="#technical">Technical Configurations</a>
	</li>
	<li>
		<a href="#other-factors">Other Factors</a>
	</li>
	<li>
		<a href="#blacklists">Blacklists</a>
	</li>
	<li>
		<a href="#tools">Tools</a>
	</li>
</ul>

<h2 id="content">Content</h2>

<p>
	First rule for not getting marked as spam: <strong>do not send spam</strong>. With modern AI, it
	is even easier to detect spammy content.
</p>

<ul>
	<li>
		Send emails that are relevant to the recipients. Make sure they have opted in to receive
		your emails.
	</li>
	<li>
		Avoid spammy formatting, like using all caps, excessive exclamation marks, or overuse of
		certain words (like "free", "urgent", etc.).
	</li>
	<li>
		Include an unsubscribe link in all emails. This is also a legal requirement in many
		jurisdictions.
	</li>
	<li>
		Configuring a <code>List-Unsubscribe</code> header is highly recommended for all list-type emails.
	</li>
</ul>

<h2 id="technical">Technical Configurations</h2>

<p>
	Proper technical configurations are crucial for email deliverability. Here are some key
	configurations to consider:
</p>

<ul>
	<li>
		<strong>SPF</strong>
		<RelayHandledTag />: DNS record that lists which IP addresses are allowed to send mail from
		the given <code>MAIL FROM</code> domain (not the <code>From</code>
		header)
	</li>
	<li>
		<strong>DKIM</strong>
		<RelayHandledTag />: Cryptographically signs outgoing mail so receivers can verify
		authenticity. The domain in the DKIM signature should match the
		<code>From</code>
		header domain. Email providers verify the signature using a public key published in DNS.
	</li>
	<li>
		<strong>DMARC</strong>: DNS record that tells receivers how to handle emails that fail SPF
		or DKIM checks. It also provides reporting capabilities. The owner of the <code>From</code>
		domain should publish a DMARC record.
	</li>
	<li>
		<strong>Reverse DNS (PTR)</strong>: Each sending IP address should have a PTR record that
		resolves back to a domain name. You should set up a PTR record for each sending IP address
		as explained in the
		<a href="/hosting/setup#ptr">Setup</a> guide.
	</li>
	<li>
		<strong>FCrDNS</strong>
		<RelayHandledTag />: The domain name used in the PTR record should resolve back to the same
		IP address.
	</li>
	<li>
		<strong>EHLO</strong>
		<RelayHandledTag />: A properly formatted FQDN presented during SMTP handshake.
	</li>
	<li>
		<strong>TLS (STARTTLS)</strong>
		<RelayHandledTag />: Ensures that emails are encrypted during transit between mail servers.
	</li>
	<li>
		<strong>Bounces & Suppressions</strong>
		<RelayHandledTag />: Properly handling bounces and avoiding sending to suppressed addresses
		helps maintain a good sender reputation.
	</li>
	<li>
		<strong>Feedback Loops</strong>: Setting up feedback loops with major email providers allows
		you to handle spam complaints effectively. Hyvor Relay creates suppressions for addresses
		that mark your emails as spam (<a href="/hosting/providers">setup required</a>)
	</li>
</ul>

<Callout type="success">
	<RelayHandledTag size="small" /> indicates configurations that Hyvor Relay manages for you. If all
	the
	<a href="/hosting/health-checks">health checks</a>
	are passing, your Hyvor Relay setup is good to go!

	<p>
		<strong>Reverse DNS (PTR)</strong> has to be configured by you, but it is covered by a health
		check - you cannot miss it!
	</p>

	<p>
		<strong>DMARC</strong> has to be set up by the owner of the sending domain (FROM address). Ask
		your end users to set it up for their domains.
	</p>
</Callout>

<h2 id="other-factors">Other Factors</h2>

<p>
	There are other factors that affect email deliverability beyond content and technical
	configurations:
</p>

<ul>
	<li>
		<strong>Domain Age</strong>: Older domains generally have better deliverability than newly
		registered ones. This applies to both the
		<a href="/hosting/setup#instance-domain">instance domain</a> and sending (FROM) domain. There
		is no quick fix for this; time is the only cure. Keep sending good emails consistently to those
		who expect them.
	</li>
	<li>
		<strong>Sender Reputation</strong>: Email providers maintain a reputation score for sending
		IP addresses and domains based on various factors like complaint rates, bounce rates, and
		spam trap hits. Suppressions, which Hyvor Relay handles, are important to avoid resending to
		addresses that have bounced or marked your emails as spam.
	</li>
	<li>
		<strong>Engagement Metrics</strong>: High open rates, click-through rates, and low complaint
		rates positively impact deliverability. Send high quality, relevant content to engaged
		recipients.
	</li>
	<li>
		<strong>Sending Volume & Frequency</strong>: Sudden spikes in sending volume can raise red
		flags. Maintain a consistent sending pattern. Hyvor Relay's API rate limits can help manage
		this.
	</li>
</ul>

<h2 id="blacklists">Blacklists</h2>

<p>
	Email providers depend on blacklists, usually maintained by a third party, to determine if an
	email is spam. They are primarily based on the <strong>sending IP address</strong> while some are
	based on the sending domain. Getting one of your IPs blacklisted can significantly affect all of
	your users' deliverability.
</p>

<p>
	Most blacklists are public. <a
		href="https://www.spamhaus.org/"
		target="_blank"
		rel="nofollow noopener">Spamhaus</a
	>,
	<a href="https://www.barracudacentral.org/" target="_blank" rel="nofollow noopener">Barracuda</a
	>, and <a href="https://www.spamcop.net/" target="_blank" rel="nofollow noopener">SpamCop</a>
	are some of the popular blacklists. They are usually
	<a href="https://en.wikipedia.org/wiki/Domain_Name_System_blocklist" target="_blank"
		>DNS Blacklists (DNSBLs)</a
	> and can be queried via DNS.
</p>

<p>
	Some providers, such as Google, Yahoo, and Microsoft, maintain their own internal blacklists.
	They cannot be queried via DNS. The <a href="/hosting/providers">Email Providers</a> page has more
	vendor-specific information.
</p>

<h3 id="blacklists-health-check">Blacklists Health Check</h3>

<p>
	Hyvor Relay has a health check to monitor the sending IPs for blacklisting on popular public
	blacklists. If any of the sending IPs are blacklisted there, an alert is shown in the Health
	section in sudo. See <a href="/hosting/health-checks#blacklists"
		>Health Check &rarr; Blacklists</a
	> for more information.
</p>

<h3 id="preventing-blacklisting">Preventing Blacklisting</h3>

<p>Here are some best practices to avoid getting blacklisted:</p>

<ul>
	<li>Send emails only to users who have opted in to receive them.</li>
	<li>
		Use double opt-in to avoid spam traps. (Spam traps are email addresses that are not used by
		real users but are used to catch spammers.)
	</li>
	<li>
		Never use purchased email lists. They are often full of spam traps and inactive addresses.
	</li>
	<li>Make sure the unsubscribing process works correctly and is easy for the users.</li>
	<li>
		<a href="/hosting/providers">Set up Feedback Loops</a> with major email providers to get notified
		when users mark your emails as spam.
	</li>
</ul>

<h3 id="removing-from-blacklists">Removing from Blacklists</h3>

<p>
	If you find your sending IPs blacklisted, follow the removal procedures provided by each
	blacklist. This usually involves identifying and fixing the issues that led to the listing, then
	submitting a delisting request. In the Blacklists Health Check section, links to the removal
	procedures are provided for each blacklist.
</p>

<p>
	Note that it is completely normal for a new sending IP or instance domain to be blacklisted
	initially. It may take some time and consistent good sending practices to build a positive
	reputation.
</p>

<h2 id="tools">Tools</h2>

<p>For testing email deliverability:</p>

<ul>
	<li>
		<a
			href="https://www.mail-tester.com?source=hyvor-relay"
			target="_blank"
			rel="nofollow noopener">Mail Tester</a
		>: Send a test email to the provided address, and it will analyze various aspects of your
		email, including spam score, SPF, DKIM, DMARC, blacklists, and more.
	</li>
	<li>
		<a
			href="https://www.mailgenius.com?source=hyvor-relay"
			target="_blank"
			rel="nofollow noopener">Mail Genius</a
		>: Similar to Mail Tester, it provides a comprehensive analysis of your email's
		deliverability factors.
	</li>
	<li>
		<a
			href="https://www.mailreach.co/email-spam-test?source=hyvor-relay"
			target="_blank"
			rel="nofollow noopener"
		>
			Mailreach
		</a>: Send emails to real inboxes across various email providers to see if they land in the
		inbox or spam folder.
	</li>
</ul>

<p>For checking blacklists:</p>

<ul>
	<li>
		<a
			href="https://mxtoolbox.com/blacklists.aspx?source=hyvor-relay"
			target="_blank"
			rel="nofollow noopener"
		>
			MXToolbox Blacklist Check</a
		>: Check if your sending IPs are listed on over 100 DNS-based blacklists.
	</li>
	<li>
		<a
			href="https://multirbl.valli.org?source=hyvor-relay"
			target="_blank"
			rel="nofollow noopener"
		>
			MultiRBL</a
		>: Another tool to check your sending IPs against multiple DNS-based blacklists.
	</li>
</ul>
