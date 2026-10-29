<h1>Microsoft Feedback Loop</h1>

<p>
	Microsoft (Outlook.com, Hotmail, Live) offers an IP-based feedback loop called the Junk Mail
	Reporting Program (JMRP). When a user marks an email as junk, Microsoft sends an ARF report to
	the registered address, as long as the email was sent from an enrolled IP address.
</p>

<p>
	Microsoft redacts the recipient's email address in these reports. Hyvor Relay records the
	complaint against the send, but the recipient cannot be added to the suppression list.
</p>

<h2 id="setup">Setup</h2>

<ol>
	<li>
		Sign in to the
		<a href="https://substrate.office.com/ip-domain-management-snds/snds" target="_blank"
			>SNDS portal</a
		> with a Microsoft account.
	</li>
	<li>
		Request access to all sending IP addresses shown in <strong>Sudo &rarr; Servers</strong>,
		and confirm the ownership as requested by Microsoft.
	</li>
	<li>
		In the JMRP settings, add the same IP addresses and set the report email address to
		<code>fbl@{'<instance-domain>'}</code>.
	</li>
</ol>
