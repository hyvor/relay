<h1>Yahoo</h1>

<p>
	Yahoo's
	<a href="https://senders.yahooinc.com/complaint-feedback-loop/" target="_blank"
		>Complaint Feedback Loop (CFL)</a
	>
	sends an ARF report for each spam complaint on emails DKIM-signed by a registered domain. Hyvor Relay
	signs all emails with the
	<a href="/hosting/setup#instance-domain">instance domain</a>, so registering it covers all
	projects. Complained recipients are added to the suppression list automatically.
</p>

<h2 id="setup">Setup</h2>

<ol>
	<li>
		Make sure the <a href="/hosting/health-checks#instance-dkim">Instance DKIM is correct</a> health
		check is passing.
	</li>
	<li>
		Sign in to the
		<a href="https://senders.yahooinc.com/complaint-feedback-loop/" target="_blank"
			>Yahoo Sender Hub</a
		>
		with a Yahoo account and start a new Complaint Feedback Loop request.
	</li>
	<li>
		Add your instance domain (e.g. <code>mail.relay.yourdomain.com</code>) as the DKIM domain.
	</li>
	<li>Set the report email address to <code>fbl@&lt;instance domain&gt;</code>.</li>
	<li>Complete Yahoo's verification steps and submit the request.</li>
</ol>
