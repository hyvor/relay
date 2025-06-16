<script>
	import { CodeBlock } from '@hyvor/design/components';
</script>

<h1>Send Distributional Emails</h1>

<p>
	<strong>Distributional emails</strong> are sent to a list of email addresses and can include personalized
	content for each recipient. Some common use cases include newsletters and marketing campaigns.
</p>

<p>
	Before getting started, familiarize yourself with <a href="/docs/send-transactional"
		>sending transactional emails</a
	> as sending distributional emails builds on that knowledge.
</p>

<h2>Sending Distributional Emails</h2>

<p>
	Sending a distributional email requires at least three HTTP requests. The API is designed to make
	sure Hyvor Relay knows about all the recipients before starting to send the emails.
</p>

<ul>
	<li>
		<a href="#creating"> Create a distribution and get its ID </a>
	</li>
	<li>
		<a href="#adding-emails"> Add emails to the distribution </a>
	</li>
	<li>
		<a href="#sending"> Send the distributional email </a>
	</li>
</ul>

<h3 id="creating">Step 1: Creating a Distribution</h3>

<p>First, you need to create a distribution.</p>

<p>
	<strong>Endpoint:</strong><br />
	<code>POST /api/console/distributions</code>
</p>

<p>
	<strong>Request:</strong>
	<CodeBlock
		code={`
    type Request {
        // a name for the distribution (ex: campaign name)
        // only used for your reference 
        name: string;

        // properties of the emails that are common to all recipients
        // highly recommended to set as many as possible
        email_defaults?: EmailRequest;

        // whether the recipient addresses should be unique
        // default: true
        // for most cases, you want to set this to true as a safety measure to avoid sending
        // the same email to the same recipient multiple times
        unique?: boolean;

        // define how to handle validation errors such as invalid email addresses
        // default: "fail"
        // validation is done when adding emails in the next step
        // "fail" will return an error if any email is invalid
        // "skip" will skip invalid emails and continue adding valid ones
        validation_strategy?: 'fail' | 'skip';
    }
`}
		language="ts"
	/>
</p>

<p>
	Note: See <a href="/docs/send-transactional#email-request-object">EmailRequest</a> object for the
	full list of properties that can be set in <code>email_defaults</code>. By setting many shared
	defaults in the distribution, you can avoid repeating them for each recipient.
</p>

<p>
	<strong>Response:</strong>
	<CodeBlock
		code={`
    type Response {
        id: number; // this ID is needed for the next steps
        created_at: number;
        name: string;
    }
`}
		language="ts"
	/>
</p>

<h3 id="adding-emails">Step 2: Adding emails to the distribution</h3>

<p>
	After creating a distribution, you can add emails to it. In a single request, you can add up to
	250 emails. Each email is an <a href="/docs/send-transactional#email-request-object"
		>EmailRequest</a
	>
	object. If a field is defined in <code>email_defaults</code> of the distribution, you can omit it
	in the EmailRequest object for each recipient. If you define a field in the EmailRequest object,
	it will override the value in <code>email_defaults</code>.
</p>

<p>
	<strong>Endpoint:</strong><br />
	<code>POST /api/console/distributions/:id/emails</code>
</p>

(<code>:id</code> is the distribution ID)

<p>
	<strong>Request:</strong>
	<CodeBlock
		code={`
    type Request = {
        // emails as an array of EmailRequest objects
        // up to 250 emails
        emails: EmailRequest[];
    }
`}
		language="ts"
	/>
</p>

<p>
	When adding emails, it is recommended to set up a <a href="send-transactional#retrying"
		>retrying mechanism with idempotency</a
	>. For idempotency, each block of emails should have a unique ID (ex:
	<code>campaign_1_block_1</code> contains the first 250 emails of the campaign).
</p>

<p>
	<strong>Response:</strong>
	<CodeBlock
		code={`
    type Response = {
        // number of emails added to the distribution
        added: number; 

        // number of emails that failed to be added
        // only if validation_strategy is set to "skip"
        // if validation_strategy is set to "fail", the response will be an error with a 422 status code
        skipped: number;

        // errors for each email that was skipped
        // indexed by the email index in the request
        errors?: Record<number, string>;
    }
`}
		language="ts"
	/>
</p>

<h3 id="sending">Step 3: Sending the Distributional Email</h3>

<p>
	After adding emails, you can send the distributional email. This will start sending the emails to
	the recipients.
</p>

<p>
	<strong>Endpoint:</strong><br />
	<code>POST /api/console/distributions/:id/send</code>
</p>

<p>
	<strong>Request:</strong>
	<CodeBlock
		code={`
    type Request = {
        // optionally, set a time (unix timestamp in seconds) 
        // in the future to send the emails
        send_at?: number;
    }
`}
		language="ts"
	/>
</p>

<p>
	<strong>Response:</strong>
	<CodeBlock
		code={`
    type Response = {}
`}
		language="ts"
	/>
</p>

<h2 id="next">What's Next?</h2>

<h3 id="webhooks">Listen to Webhooks</h3>

<p>
	Email sending is an asynchronous process. Dependending on the number of emails and the latencies
	between the SMTP servers, it can take some time to deliver the emails. Therefore, you should set
	up <a href="webhooks">webhooks</a> to listen to the events related to the distributional emails.
</p>
