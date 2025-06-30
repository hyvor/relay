<script>
	import { CodeBlock, TabbedCodeBlock } from '@hyvor/design/components';
</script>

<h1>Send Emails</h1>

<p>
	This page explains how to send emails using the Console API. Before getting started, make sure to <a
		href="/docs">set up a project</a
	>
	and familiarize yourself with the
	<a href="/docs/api-console">Console API</a>.
</p>

<ul>
	<li>
		<a href="#sending">Sending Emails</a>
	</li>
	<li>
		<a href="#retrying">Retrying Failed Requests</a>
	</li>
	<li>
		<a href="#rate-limit">Rate Limiting</a>
	</li>
	<li>
		<a href="#limits">Other Limits</a>
	</li>
</ul>

<h2 id="sending">Sending Emails</h2>

<p>
	<strong>Endpoint:</strong><br />
	<code>POST /api/console/sends</code>
</p>

<p>
	<strong>Request:</strong>
	<CodeBlock
		code={`
    type Request = SendRequest;
`}
		language="ts"
	/>
</p>

<p id="email-request-object">
	This is an <code>SendRequest</code> object, which is used to define the email you want to send.
</p>

<TabbedCodeBlock tabs={['Types', 'JSON Example']}>
	<CodeBlock
		code={`
interface SendRequest {
	// The email address of the sender (required)
	// the domain of the email address must be verified
	from: Address;

	// The email address of the recipient (required)
	to: Address;

	// The subject of the email
	subject?: string;

	// The body of the email in HTML format
	// required if body_text is not provided
	body_html?: string;

	// The body of the email in plain text format
	// required if body_html is not provided
	body_text?: string;

	// additional headers
	headers?: Record<string, string>;
}
	
type Address = string | {
	name?: string;
	email: string;
};
`}
		language="ts"
	/>

	<CodeBlock
		code={`
{
	// email address with a name
	"from": {
		"name": "HYVOR",
		"email": "contact@hyvor.com"
	},

	// email address without a name
	"to": "user@example.org",

	"subject": "Welcome to HYVOR",
	"body_html": "<h1>Welcome to HYVOR</h1><p>Thank you for signing up!</p>",
	"body_text": "Welcome to HYVOR\\n\\nThank you for signing up!",

	"headers": {
		"X-Custom-Header": "Custom Value"
	}
}
`}
		language="json"
	/>
</TabbedCodeBlock>

<h2 id="retrying">Retrying Failed Requests</h2>

<h3>Implement retrying</h3>

<p>
	The HTTP request to send a transactional email may fail due to network issues or other temporary
	problems. To prevent losing emails, we recommend configuring your application to retry
	automatically if the API returns a non-2xx status code with incremental backoff. For example, you
	can retry the request up to 3 times with a delay of 1 second, 2 seconds, and 4 seconds between
	retries.
</p>

<h3>Idempotency</h3>

<p>
	You may receive a 500 error from the API even when the email is accepted and queued (for example,
	when the network connection right before sending back a response). To prevent sending the same
	email multiple times, use the <code>X-Idempotency-Key</code> header. This header should contain a unique
	idempotency key for each email you send.
</p>

<p>Some idempotency key examples:</p>

<ul>
	<li>
		<code>
			welcome-email-{'{userId}'}
		</code> <br />(since the welcome email is sent only once to a user)
	</li>
	<li>
		<code>
			order-confirmation-{'{orderId}'}
		</code> <br />(since the order confirmation email is sent only once for an order)
	</li>
</ul>

<!-- <h2 id="rate-limit">Rate Limiting</h2>

<p>TODO</p> -->

<h2 id="limits">Other Limits</h2>

<ul>
	<li>
		<strong>Total email size</strong> is limited to <strong>10MB</strong> <br /> (including headers,
		body, and attachments).
	</li>
	<li>
		<strong>HTML body size</strong> is limited to <strong>2MB</strong>.
	</li>
	<li>
		<strong>Plain text body size</strong> is limited to <strong>2MB</strong>.
	</li>
	<li>
		<strong>Subject</strong> is limited to <strong>998</strong> characters.
	</li>
	<li>
		<strong>Attachments</strong> are limited to <strong>10</strong> per email. There is no size limit
		for each attachment, but the total email size must not exceed 10MB.
	</li>
</ul>
