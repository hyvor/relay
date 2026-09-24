<h1>Microsoft</h1>

<p>
	Microsoft's Junk Mail Reporting Program (JMRP) sends an ARF report for each spam complaint on
	emails sent from registered IP addresses (Outlook.com, Hotmail, Live). Complained recipients are
	added to the suppression list automatically.
</p>

<h2 id="setup">Setup</h2>

<ol>
	<li>
		Sign in to
		<a href="https://sendersupport.olc.protection.outlook.com/snds/JMRP.aspx" target="_blank"
			>JMRP</a
		>
		with a Microsoft account.
	</li>
	<li>
		Add all sending IP addresses shown in <strong>Sudo &rarr; Servers</strong>. Microsoft sends
		an authorization email to an address associated with the IPs to confirm ownership.
	</li>
	<li>Set the report email address to <code>fbl@&lt;instance domain&gt;</code>.</li>
	<li>Choose the ARF report format and submit.</li>
</ol>
