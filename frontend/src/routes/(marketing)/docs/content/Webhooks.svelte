<script>
	import { CodeBlock } from '@hyvor/design/components';
</script>

<h1>Webhooks</h1>

<p>
	Webhooks are a way to receive HTTP POST requests from Hyvor Relay when certain events occur.
	They are useful to sync state (email sending status) between Hyvor Relay and your application or
	to trigger actions in your application based on events in Hyvor Relay.
</p>

<ul>
	<li>
		<a href="#events">Events & Payloads</a>
	</li>
	<li>
		<a href="#retrying">Retrying</a>
	</li>
</ul>

<h2 id="events">Events & Payloads</h2>

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
	<br />
	<li>
		<a href="#domain-verified">domain.verification.changed</a>
	</li>
	<li>
		<a href="#domain-warned">domain.warned</a>
	</li>
	<li>
		<a href="#domain-unverified">domain.unverified</a>
	</li>
	<br />
	<li>
		<a href="#suppression-created">suppression.created</a>
	</li>
	<li>
		<a href="#suppression-deleted">suppression.deleted</a>
	</li>
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
	attempt: SendAttempt;
}
`}
	language="ts"
/>

<p>
	Objects: <a href="/docs/api-console#send-object">Send</a>,
	<a href="/docs/api-console#send-attempt-object">SendAttempt</a>
</p>

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
	attempt: SendAttempt;
}
`}
	language="ts"
/>

<p>
	Objects: <a href="/docs/api-console#send-object">Send</a>,
	<a href="/docs/api-console#send-attempt-object">SendAttempt</a>
</p>

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
	attempt: SendAttempt | null;
	bounce: Bounce;
}
`}
	language="ts"
/>

<p>
	Objects: <a href="/docs/api-console#send-object">Send</a>,
	<a href="/docs/api-console#send-attempt-object">SendAttempt</a>
</p>

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

<h3 id="domain-verified">domain.verified</h3>

<p>
	This event is triggered when a domain is successfully verified. This means that the domain has
	been configured correctly, and you can start sending emails from this domain. <code
		>dkim_verified</code
	>
	is set to <code>true</code> in the <code>Domain</code> object.
</p>

<CodeBlock
	code={`
{
	domain: Domain;
}
`}
	language="ts"
/>

<p>
	Objects: <a href="/docs/api-console#domain-object">Domain</a>
</p>

<h3 id="domain-warned">domain.warned</h3>

<p>
	This event is triggered when a domain is put on warning status. This can happen if the DKIM TXT
	record is removed or changed or if the domain is flagged for spam or other issues. You must
	resolve this issue within 24 hours, or the domain will be <a href="#domain-unverified"
		>marked as unverified</a
	>.
</p>

<CodeBlock
	code={`
{
	domain: Domain;
}
`}
	language="ts"
/>

<p>
	Objects: <a href="/docs/api-console#domain-object">Domain</a>
</p>

<h3 id="domain-unverified">domain.unverified</h3>

<p>
	This event is triggered when a domain is marked as unverified. This can happen if the DKIM TXT
	record is removed or changed, or if the domain is flagged for spam or other issues and not
	resolved within 24 hours. You will not be able to send emails from this domain until the domain
	is fully verified.
</p>

<h2 id="retrying">Retrying</h2>

<p>
	When Hyvor Relay sends a webhook, it expects a <code>2xx</code> HTTP response code from your
	application. If it does not receive a <code>2xx</code> response, it will retry sending the webhook
	up to 6 times with the following intervals between retries:
</p>

<ul>
	<li>1 minute</li>
	<li>5 minutes</li>
	<li>15 minutes</li>
	<li>1 hour</li>
	<li>4 hours</li>
	<li>24 hours</li>
</ul>

<p>
	If all retries fail, the webhook delivery will be marked as failed, and you can view the
	delivery status in the Hyvor Relay Console, including the error message received from your
	server.
</p>
