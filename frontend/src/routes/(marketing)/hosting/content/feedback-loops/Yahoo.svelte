<h1>Yahoo Feedback Loop</h1>

<p>
	Yahoo offers a domain-based feedback loop called the
	<a href="https://senders.yahooinc.com/complaint-feedback-loop/" target="_blank"
		>Complaint Feedback Loop (CFL)</a
	>. When a Yahoo user marks an email as spam, Yahoo sends an ARF report to the registered
	address, as long as the email is DKIM-signed by an enrolled domain.
</p>

<p>
	Hyvor Relay signs all emails with the DKIM key of the
	<a href="/hosting/setup#instance-domain">instance domain</a>, so enrolling the instance domain
	covers all projects. When a report is received, the recipient is automatically added to the
	project's suppression list.
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
		> with a Yahoo account.
	</li>
	<li>
		In the <strong>Domains</strong> section, add your instance domain (e.g.,
		<code>mail.relay.yourdomain.com</code>). To verify it, add the <code>TXT</code> record shown
		by Yahoo in <strong>Sudo &rarr; Settings &rarr; DNS &rarr; Custom DNS Records</strong>.
	</li>
	<li>
		In <strong>Manage Services &rarr; Complaint Feedback Loop</strong>, enroll the instance
		domain and set the report email address to <code>fbl@{'<instance-domain>'}</code>.
	</li>
	<li>
		Yahoo sends a verification code to the report email address. You can find it in
		<strong>Sudo &rarr; Debug &rarr; Bounce/FBL Logs</strong>. Enter the code in the Sender Hub
		to complete the enrollment.
	</li>
</ol>
