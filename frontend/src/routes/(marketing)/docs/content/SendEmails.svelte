<script>
	import { CodeBlock, TabbedCodeBlock, Table, TableRow } from '@hyvor/design/components';
</script>

<h1>Send Emails</h1>

<p>
	This page explains how to send emails using the Console API. Before getting started, make sure
	to <a href="/docs">set up a project</a>
	and familiarize yourself with the
	<a href="/docs/api-console">Console API</a>.
</p>

<ul>
	<li>
		<a href="#sending">Sending Emails</a>
	</li>
	<li>
		<a href="#retrying">Retrying Failed Requests & Idempotency</a>
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

	attachments?: Attachment[];
}
	
type Address = string | {
	name?: string;
	email: string;
};

type Attachment = {
	content: string; // base64 encoded
	name?: string;
	content_type?: string; // MIME type
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
	},

	"attachments": [
		{
			"content": "SFlWT1IgUm9ja3Mh",
			"name": "hello.txt",
			"content_type": "text/plain"
		}
	]
}
`}
		language="json"
	/>
</TabbedCodeBlock>

<h2 id="retrying">Retrying Failed Requests & Idempotency</h2>

<h3>Implement retrying</h3>

<p>
	The HTTP request to send an email may fail due to network issues or other temporary problems. To
	prevent losing emails, we recommend configuring your application to retry automatically if the
	API returns a non-2xx status code with incremental backoff.
</p>

<p>
	The recommended retry strategy is to send emails asynchronously and retry up to 3 times with an
	exponential backoff strategy (e.g., 30s, 2m, 5m). If the request fails after 3 retries, you can
	log the error and notify your team to investigate the issue.
</p>

<p>
	If your setup sends emails synchronously (ex: within a web request), you can still implement
	retrying with a smaller timeouts, such as 1s, 2s, and 5s.
</p>

<h3 id="idempotency">Idempotency</h3>

<p>
	When retrying it is possible that your request was already accepted and queued by the API, but
	the response was not received by your application due to a network issue. To prevent sending the
	same email multiple times, you can use the <code>X-Idempotency-Key</code> header in your request.
	This header should contain a unique idempotency key for each email you send.
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
		</code> <br />(since the order confirmation email is sent only once for an order). Note that
		you should use a new key if you have a "resend" option for an email.
	</li>
</ul>

<p>
	Idempotency keys are saved for 24 hours. If you retry a request with the same idempotency key
	before the 24-hour period ends, the API will return the same response as the first request
	without actually processing it. If the idempotency key is not found, the API will process the
	request as usual and return a new response.
</p>

<p>
	<code>X-Idempotency-Short-Circuit</code> header is set to <code>true</code> if the response was created
	using a previously processed request with the same idempotency key.
</p>

<h3 id="response-status-codes">Response Status Codes</h3>

<Table columns="1fr 2fr 2fr">
	<TableRow head>
		<div>Code</div>
		<div>Description</div>
		<div>What to do</div>
	</TableRow>
	<TableRow>
		<div>200</div>
		<div>Email sent queued.</div>
		<div>No action needed.</div>
	</TableRow>
	<TableRow>
		<div>4xx</div>
		<div>Client/request error.</div>
		<div>Correct the request and retry with a new idempotency key.</div>
	</TableRow>
	<TableRow>
		<div>429</div>
		<div>Too Many Requests.</div>
		<div>
			Retry after the specified <code>Retry-After</code> header value. If you are using an idempotency
			key, you can retry with the same key.
		</div>
	</TableRow>
	<TableRow>
		<div>5xx</div>
		<div>Server error.</div>
		<div>Retry the request with the same idempotency key after a short delay.</div>
	</TableRow>
</Table>

<p>
	If the server returns a 5xx status code, idempotency keys are not saved, and retrying the
	request is recommended. For 4xx status codes, except 429, the idempotency key is saved, and
	retrying the request will not have any effect unless you change the request and use a new
	idempotency key.
</p>

<h2 id="rate-limit">Rate Limiting</h2>

<!-- 

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
