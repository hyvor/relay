<script>
	import { CodeBlock } from '@hyvor/design/components';
</script>

<h1>Webhooks</h1>

<h2 id="events">Events & Payload</h2>

<ul>
	<li>
		<a href="#send-accepted">send.accepted</a>
	</li>
	<li>
		<a href="#send-deferred">send.deferred</a>
	</li>
	<li>
		<a href="#send-bounced">send.bounced</a>
	</li>
	<li>
		<a href="#send-complained">send.complained</a>
	</li>
	<li>suppression.created</li>
	<li>suppression.deleted</li>
	<li>domain.created</li>
	<li>domain.verified</li>
	<li>domain.deleted</li>
</ul>

<h3 id="send-accepted">send.accepted</h3>

<p>
	<code>send.accepted</code> is triggered when an email is accepted by the recipient SMTP server.
	This can only be triggered once for a send. It indicates that the email has been successfully
	delivered to the recipient's server, but it does not guarantee that the email has been delivered
	to the recipient's inbox. <code>send.bounced</code> or <code>send.complained</code> can be triggered
	later if the email is not delivered to the recipient's inbox (ex: when the mailbox is full, or if
	the email provider's spam detector detects the mail as spam) or if the recipient marks it as spam.
</p>

<CodeBlock
	code={`
{
	send: Send;
	last_attempt: SendAttempt;
}
`}
	language="ts"
/>

<h3 id="send-deferred">send.deferred</h3>

<p>
	<code>send.deferred</code> is triggered when an email is temporarily deferred by the recipient
	SMTP server. This can happen for various reasons, such as the recipient's server being busy or
	because of
	<a href="https://en.wikipedia.org/wiki/Greylisting_(email)">greylisting</a>. Hyvor Relay will
	retry sending the email a few more times before giving up. You can expect a
	<code>send.accepted</code> or
	<code>send.bounced</code> event later.
</p>

<CodeBlock
	code={`
{
	send: Send;
	last_attempt: SendAttempt;
}
`}
	language="ts"
/>

<h3 id="send-bounced">send.bounced</h3>

<p>
	<code>send.bounced</code> is triggered when an email is permanently rejected by the recipient SMTP
	server. This can happen for various reasons, such as the recipient's email address not existing or
	the recipient's server rejecting the email due to spam filters.
</p>

<ul>
	<li>
		<strong> Synchronous Bounces: </strong> In some cases, bounces are detected immediately in
		the SMTP conversation when sending the email. In such cases, the
		<code>send.bounced</code> event is triggered immediately.
	</li>
	<li>
		<strong> Asynchronous Bounces: </strong> In other cases, the bounce is detected later, such
		as when the recipient's server sends a bounce notification (Delivery Status Notification,
		DSN) after some time. In such cases, a <code>send.accepted</code> event is triggered first,
		followed by the <code>send.bounced</code> event when the bounce is detected.
	</li>
</ul>

<p>
	If we detect a hard bounce (permanent failure) for a send, the email address is automatically
	added to your project's suppression list. This means that you cannot send emails to that email
	address again unless you manually remove it from the suppression list. This is to prevent
	sending emails to invalid email addresses, which can harm your sender reputation.
</p>

<CodeBlock
	code={`
{
	send: Send;
	last_attempt: SendAttempt | null;
	bounce: Bounce;
}
`}
	language="ts"
/>

<h3 id="send-complained">send.complained</h3>

<p>
	When a recipient marks an email as spam, the email provider sends a complaint, called a Feedback
	Loop (FBL), to Hyvor Relay. Hyvor Relay is configured to receive FBLs from major email
	providers.bind: We process these complaints and trigger the <code>send.complained</code> event. This
	event indicates that the recipient has marked the email as spam or junk. When this event is triggered,
	the email address is automatically added to your project's suppression list, similar to when a hard
	bounce occurs.
</p>

<CodeBlock
	code={`
{
	send: Send;
	complaint: Complaint;
}
`}
	language="ts"
/>
