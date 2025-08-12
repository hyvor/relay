<h1>Deliverability</h1>

<p>
	In the 90s, anyone could send emails to anyone else without any restrictions. The <a
		href="https://en.wikipedia.org/wiki/Laurence_Canter_and_Martha_Siegel"
		target="_blank"
	>
		Green Card spam incident</a
	> changed things. Email providers introduced spam filters (well, for a good reason), which led to
	the current state of sophisticated email filters.
</p>

<p>
	This guide will help you understand how to improve the deliverability of emails sent via Hyvor
	Relay. Remember, there are some things that you who hosts Hyvor Relay should do, and some things
	that your users who send emails via Hyvor Relay (maybe it's still you) should do.
</p>

<p>Jump to each section:</p>

<ul>
	<li>
		<a href="#basics">Basics</a>
	</li>
	<li>
		<a href="#tools">Tools</a>
	</li>
</ul>

<h2 id="basics">Basics</h2>

<p>For best deliverability, you should understand three main concepts:</p>

<ul>
	<li>
		<a href="#content">Content</a>
	</li>
	<li>
		<a href="#blacklists">Blacklists</a>
	</li>
	<li>
		<a href="#technical">Technical Configurations</a>
	</li>
</ul>

<h3 id="content">Content</h3>

<p>
	First rule for not getting marked as spam: Do not send spam. With modern AI, it is even easier
	to detect spammy content.
</p>

<ul>
	<li>Send emails that are relevant to the recipients.</li>
	<li>
		Avoid spammy formatting, like using all caps, excessive exclamation marks, or overuse of
		certain words (like "free", "urgent", etc.).
	</li>
	<li>
		Include an unsubscribe link in all emails. This is also a legal requirement in many
		jurisdictions.
	</li>
	<li>
		Configuring a <code>List-Unsubscribe</code> header is highly recommended.
	</li>
</ul>

<h3 id="blacklists">Blacklists</h3>

<p>
	Email providers depend on blacklists to determine if an email is spam. They are primarily based
	on the sending IP address while some are based on the sending domain. Getting one of your IPs
	blacklisted can significantly affect all of your users' deliverability.
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

<h4 id="supported-blacklists">Supported Blacklists on Hyvor Relay</h4>

<p>
	Hyvor Relay queries the following IP blacklists every hour to determine if any of the sending
	IPs are blocked. You can see the results in sudo. Email notifications are also sent.
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

<p>Use these free external tools to check many other blacklists at once:</p>

<ul>
	<li>
		<a href="https://mxtoolbox.com/blacklists.aspx" target="_blank" rel="nofollow noopener"
			>MXToolbox Blacklist Check</a
		>
	</li>
	<li>
		<a href="https://multirbl.valli.org/" target="_blank" rel="nofollow noopener">MultiRBL</a>
	</li>
</ul>

<p>To avoid getting blacklisted,</p>

<ul>
	<li>Send emails only to users who have opted in to receive them.</li>
	<li>
		Use double opt-in to avoid spam traps. (Spam traps are email addresses that are not used by
		real users but are used to catch spammers.)
	</li>
	<li>
		Do not use purchased email lists. They are often full of spam traps and inactive addresses.
	</li>
	<li>Make sure the unsubscribing process works correct and is easy for the users.</li>
	<li>
		Set up <a href="/hosting/providers">Feedback Loops</a> (you need to register with the providers)
		and automatically suppress those users who mark your emails as spam (Hyvor Relay handles this).
	</li>
</ul>
