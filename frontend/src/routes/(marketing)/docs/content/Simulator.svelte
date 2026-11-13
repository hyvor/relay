<script>
	import { CodeBlock, Table, TableRow } from '@hyvor/design/components';
</script>

<h1>Testing with the Simulator</h1>

<p>
	HYVOR runs a public SMTP simulator at <code>simulator.relay.hyvor.com</code>. Emails sent to
	this domain are not delivered to any mailbox. Instead, the simulator responds with a pre-defined
	result based on the recipient address, such as a successful delivery, a bounce, or a complaint.
	You can use it to test how your application handles each case (for example, your
	<a href="/docs/webhooks">webhook</a> handlers) without sending emails to real people or harming your
	sender reputation.
</p>

<p>
	The simulator works with both the Cloud and self-hosted instances. If you prefer, you can also
	run your own copy using <a href="https://github.com/hyvor/smtp-simulator" target="_blank"
		>hyvor/smtp-simulator</a
	>.
</p>

<h2 id="usage">Usage</h2>

<p>
	Send an email as usual, using the <a href="/docs/send-emails">API</a> or
	<a href="/docs/send-emails-smtp">SMTP</a>, from a verified domain to one of the addresses listed
	below. For example, to simulate a hard bounce:
</p>

<CodeBlock
	code={`
{
    "from": "you@your-verified-domain.com",
    "to": "missing@simulator.relay.hyvor.com",
    "subject": "Testing a hard bounce",
    "body_text": "Hello!"
}
`}
	language="json"
/>

<p>
	The local part of the address (the part before <code>@</code>) decides the result. It is
	case-insensitive. Any other local part is rejected with <code>550 Unknown local part</code>. The
	<strong>Result</strong>
	column shows the recipient status changes you will see in
	<strong>Console &rarr; Sends</strong> and in <a href="/docs/webhooks">webhooks</a>.
</p>

<h2 id="sync">Synchronous Responses</h2>

<p>
	These addresses respond immediately in the SMTP conversation, at the <code>RCPT TO</code> command.
	This is how most email servers reject emails.
</p>

<Table columns="1.3fr 2fr 1fr 1.5fr">
	<TableRow head>
		<div>Address</div>
		<div>Simulates</div>
		<div>Response</div>
		<div>Result</div>
	</TableRow>
	<TableRow>
		<div><code>accept@</code></div>
		<div>Successful delivery</div>
		<div><code>250 2.0.0</code></div>
		<div>accepted</div>
	</TableRow>
	<TableRow>
		<div><code>busy@</code></div>
		<div>Mailbox busy</div>
		<div><code>450 4.2.1</code></div>
		<div>deferred &rarr; failed</div>
	</TableRow>
	<TableRow>
		<div><code>tempfail@</code></div>
		<div>Temporary failure</div>
		<div><code>451 4.3.0</code></div>
		<div>deferred &rarr; failed</div>
	</TableRow>
	<TableRow>
		<div><code>missing@</code></div>
		<div>Mailbox does not exist</div>
		<div><code>550 5.1.1</code></div>
		<div>bounced, suppressed</div>
	</TableRow>
	<TableRow>
		<div><code>disabled@</code></div>
		<div>Mailbox disabled</div>
		<div><code>550 5.1.2</code></div>
		<div>bounced, suppressed</div>
	</TableRow>
	<TableRow>
		<div><code>spam@</code></div>
		<div>Rejected by a spam filter</div>
		<div><code>550 5.7.1</code></div>
		<div>bounced</div>
	</TableRow>
</Table>

<p id="deferred">
	Hyvor Relay retries deferred emails up to 7 times over about 31 hours (after 15 minutes, 1 hour,
	2 hours, 4 hours, 8 hours, and 16 hours). Since the simulator always responds the same way, the
	email is marked as <a href="/docs/webhooks#send-recipient-failed">failed</a> after the last attempt.
</p>

<h2 id="atdata">Responses After DATA</h2>

<p>
	These addresses accept the recipient at the <code>RCPT TO</code> command, but reject the email
	after its content is sent (after the <code>DATA</code> command). Some email servers reject emails
	at this stage, for example, after scanning the content.
</p>

<Table columns="1.3fr 2fr 1fr 1.5fr">
	<TableRow head>
		<div>Address</div>
		<div>Simulates</div>
		<div>Response</div>
		<div>Result</div>
	</TableRow>
	<TableRow>
		<div><code>busy+atdata@</code></div>
		<div>Mailbox busy</div>
		<div><code>450 4.2.1</code></div>
		<div>deferred &rarr; failed</div>
	</TableRow>
	<TableRow>
		<div><code>tempfail+atdata@</code></div>
		<div>Temporary failure</div>
		<div><code>451 4.3.0</code></div>
		<div>deferred &rarr; failed</div>
	</TableRow>
	<TableRow>
		<div><code>missing+atdata@</code></div>
		<div>Mailbox does not exist</div>
		<div><code>550 5.1.1</code></div>
		<div>bounced, suppressed</div>
	</TableRow>
	<TableRow>
		<div><code>disabled+atdata@</code></div>
		<div>Mailbox disabled</div>
		<div><code>550 5.1.2</code></div>
		<div>bounced, suppressed</div>
	</TableRow>
	<TableRow>
		<div><code>spam+atdata@</code></div>
		<div>Rejected by a spam filter</div>
		<div><code>550 5.7.1</code></div>
		<div>bounced</div>
	</TableRow>
</Table>

<p>
	Note that the <code>DATA</code> response applies to all recipients of the email on the simulator
	domain. For example, if you send one email to both <code>accept@</code> and
	<code>missing+atdata@</code>, both recipients will be bounced. Use a separate email for each
	scenario.
</p>

<h2 id="async">Asynchronous Bounces & Complaints</h2>

<p>
	These addresses accept the email, and a moment later, the simulator sends a bounce (<a
		href="https://datatracker.ietf.org/doc/html/rfc3464"
		target="_blank">DSN</a
	>) or a complaint (<a href="https://datatracker.ietf.org/doc/html/rfc5965" target="_blank"
		>ARF</a
	>) back to Hyvor Relay. This is how many email providers report bounces and spam complaints. The
	<strong>Response</strong> column shows the status code in the bounce.
</p>

<Table columns="1.3fr 2fr 1fr 1.5fr">
	<TableRow head>
		<div>Address</div>
		<div>Simulates</div>
		<div>Response</div>
		<div>Result</div>
	</TableRow>
	<TableRow>
		<div><code>missing+async@</code></div>
		<div>Mailbox does not exist</div>
		<div><code>5.1.1</code></div>
		<div>accepted &rarr; bounced, suppressed</div>
	</TableRow>
	<TableRow>
		<div><code>disabled+async@</code></div>
		<div>Mailbox disabled</div>
		<div><code>5.1.2</code></div>
		<div>accepted &rarr; bounced, suppressed</div>
	</TableRow>
	<TableRow>
		<div><code>spam+async@</code></div>
		<div>Rejected by a spam filter</div>
		<div><code>5.7.1</code></div>
		<div>accepted</div>
	</TableRow>
	<TableRow>
		<div><code>complaint@</code></div>
		<div>Recipient marks the email as spam</div>
		<div>-</div>
		<div>accepted &rarr; complained, suppressed</div>
	</TableRow>
</Table>

<p>
	Asynchronous bounces caused by spam filters (<code>5.7.x</code>) are recorded by Hyvor Relay,
	but they do not change the recipient status. Therefore, <code>spam+async@</code> stays
	<code>accepted</code>.
</p>

<h2 id="custom">Custom Responses</h2>

<p>
	To get any SMTP response you want, send the email to
	<code>custom+&lt;hex&gt;@simulator.relay.hyvor.com</code>, where <code>&lt;hex&gt;</code> is the hex-encoded
	JSON of the following object:
</p>

<CodeBlock
	code={`
type CustomResponse = {
    // SMTP status code (200-599)
    status: number;

    // text sent after the status code, as it is
    // include an enhanced status code here if you need one
    // ex: "5.7.1 bad reputation" -> "550 5.7.1 bad reputation"
    message?: string;

    // when to respond
    // "rcpt" (default): at the RCPT TO command
    // "data": after the DATA command (status must be 4xx or 5xx)
    at?: 'rcpt' | 'data';
}
`}
	language="ts"
/>

<p>For example, to get a <code>550 5.7.1 bad reputation</code> response:</p>

<CodeBlock
	code={`
HEX=$(printf '%s' '{"status":550,"message":"5.7.1 bad reputation"}' | xxd -p | tr -d '\\n')
echo "custom+$HEX@simulator.relay.hyvor.com"
`}
	language="bash"
/>

<p>
	If the hex or JSON is invalid, <code>status</code> is not between 200 and 599, <code>at</code>
	is not <code>rcpt</code> or <code>data</code>, or a 2xx status is used with
	<code>"at": "data"</code>, the simulator responds with <code>501</code>.
</p>

<h2 id="suppressions">Suppressions</h2>

<p>
	The simulator is treated like any other email server. When a recipient bounces with
	<code>5.1.1</code>, <code>5.1.2</code>, <code>5.1.3</code>, or <code>5.5.0</code>, or complains,
	the address is added to your project's suppression list (marked as suppressed in the tables
	above). Any later email to that address is marked as
	<a href="/docs/webhooks#send-recipient-suppressed">suppressed</a> without being sent to the simulator.
</p>

<p>
	To test the same address again, delete the suppression at
	<strong>Console &rarr; Suppressions</strong> or using the
	<a href="/docs/api-console#delete-suppression">Console API</a>.
</p>
