<script>
	import { CodeBlock, Tag } from '@hyvor/design/components';
	import Scope from './component/Scope.svelte';
</script>

<h1>Console API</h1>

<p>
	The Console API provides a way to interact with the features of the Console programmatically. It
	is also used to <a href="/docs/send-emails">send emails</a>.
</p>

<p>
	To get started, create an API key from the Console. Note that the API key is project-specific,
	meaning it can only be used with the project it was created in.
</p>

<h2 id="api-usage">API Usage</h2>
<ul>
	<li>
		<strong>API URL</strong>: <code>https://relay.hyvor.com/api/console</code><br />
	</li>
	<li>
		<strong>Content-Type</strong>: <code>application/json</code> (both for requests and responses)
	</li>
	<li>
		<strong>Authentication</strong>: Set the <code>Authorization</code> header with your API key
		as a Bearer token:

		<CodeBlock
			code={`
Authorization: Bearer <your_api_key>
`}
			language={null}
		/>
	</li>
</ul>

<h2 id="scopes">Scopes</h2>

<p>
	Scopes are used to control access to endpoints of the Console API. When creating an API key, you
	can select the scopes that the key will have access to. The available scopes are:
</p>

<ul>
	<li>
		<strong>sends.read</strong>
	</li>
	<li>
		<strong>sends.write</strong>
	</li>
	<li>
		<strong>sends.send</strong>
	</li>
	<li>
		<strong>domains.read</strong>
	</li>
	<li>
		<strong>domains.write</strong>
	</li>
	<li>
		<strong>webhooks.read</strong>
	</li>
	<li>
		<strong>webhooks.write</strong>
	</li>
	<li>
		<strong>api_keys.read</strong>
	</li>
	<li>
		<strong>api_keys.write</strong>
	</li>
	<li>
		<strong>suppressions.read</strong>
	</li>
	<li>
		<strong>suppressions.write</strong>
	</li>
	<li>
		<strong>analytics.read</strong>
	</li>
</ul>

<p>Each endpoint requires specific scopes to be included in the API key.</p>

<h2 id="endpoints">Endpoints</h2>

<ul>
	<li>
		<a href="#sends">Sends</a> (Emails)
	</li>
	<li>
		<a href="#domains">Domains</a>
	</li>
	<li>
		<a href="#webhooks">Webhooks</a>
	</li>
	<li>
		<a href="#api-keys">API Keys</a>
	</li>
	<li>
		<a href="#suppressions">Suppressions</a>
	</li>
	<li>
		<a href="#analytics">Analytics</a>
	</li>
</ul>

<h3 id="sends">Sends (Emails)</h3>

<p>Endpoints:</p>

<ul>
	<li>
		<a href="#send-email">POST /sends</a>
		<Scope>Idempotency Supported</Scope> - Send an email
	</li>
	<li>
		<a href="#get-sends">GET /sends</a> - Get sent emails
	</li>
	<li>
		<a href="#get-send">GET /sends/:id</a> - Get a specific sent email by ID
	</li>
</ul>

<p>Objects:</p>

<ul>
	<li>
		<a href="#send-object">Send Object</a>
	</li>
	<li>
		<a href="#send-attempt-object">SendAttempt Object</a>
	</li>
</ul>

<h4 id="send-email">Send Email</h4>

<p>
	<code>POST /sends</code> (scope: <strong>sends.send</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        from: string | { email: string, name?: string },
        to: string | { email: string, name?: string },
        subject?: string,
        body_html?: string,
        body_text?: string,
        headers?: Record<string, string>,
        attachments?: Array<{
            content: string, // base64 encoded
            name?: string,
            content_type?: string
        }>
    }
    type Response = {
        id: number,
        message_id: string
    }
`}
	language="ts"
/>

<h4 id="get-sends">Get Sends</h4>

<p>
	<code>GET /sends</code> (scope: <strong>sends.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        limit?: number, // Optional. Default is 50
        offset?: number, // Optional. Default is 0
        status?: 'queued' | 'processing' | 'accepted' | 'bounced' | 'complained', // Optional. Filter by status
        from_search?: string, // Optional. Search from address
        to_search?: string // Optional. Search to address
    }
    type Response = Send[]
`}
	language="ts"
/>

<h4 id="get-send">Get Send</h4>

<p>
	<code>GET /sends/uuid/:uuid</code>
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = Send // includes attempts array
`}
	language="ts"
/>

<h3 id="domains">Domains</h3>

<p>Endpoints:</p>

<ul>
	<li>
		<a href="#get-domains">GET /domains</a> - Get domains of the project
	</li>
	<li>
		<a href="#create-domain">POST /domains</a> - Create a new domain for the project
	</li>
	<li>
		<a href="#get-domain">GET /domains/:id</a> - Get a specific domain by ID
	</li>
	<li>
		<a href="#delete-domain">DELETE /domains/:id</a> - Delete a domain from the project
	</li>
</ul>

<p>Objects:</p>

<ul>
	<li>
		<a href="#domain-object">Domain Object</a>
	</li>
</ul>

<h4 id="get-domains">Get Domains</h4>

<p>
	<code>GET /domains</code> (scope: <strong>domains.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        limit?: number, // Optional. Default is 50
        offset?: number, // Optional. Default is 0
        search?: string, // Optional. Search by domain name
    }
    type Response = Domain[]
`}
	language="ts"
/>

<h4 id="create-domain">Create Domain</h4>

<p>
	<code>POST /domains</code> (scope: <strong>domains.write</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        domain: string
    }
    type Response = Domain
`}
	language="ts"
/>

<h4 id="get-domain">Get Domain</h4>

<p>
	<code>GET /domains/:id</code> (scope: <strong>domains.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = Domain
`}
	language="ts"
/>

<h4 id="delete-domain">Delete Domain</h4>

<p>
	<code>DELETE /domains/:id</code> (scope: <strong>domains.write</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = {}
`}
	language="ts"
/>

<h3 id="webhooks">Webhooks</h3>

<p>Endpoints:</p>

<ul>
	<li>
		<a href="#get-webhooks">GET /webhooks</a> - Get webhooks for the project
	</li>
	<li>
		<a href="#create-webhook">POST /webhooks</a> - Create a new webhook
	</li>
	<li>
		<a href="#update-webhook">PATCH /webhooks/:id</a> - Update a webhook
	</li>
	<li>
		<a href="#delete-webhook">DELETE /webhooks/:id</a> - Delete a webhook
	</li>
	<li>
		<a href="#get-webhook-deliveries">GET /webhooks/deliveries</a> - Get webhook deliveries
	</li>
</ul>

<p>Objects:</p>

<ul>
	<li>
		<a href="#webhook-object">Webhook Object</a>
	</li>
	<li>
		<a href="#webhook-delivery-object">WebhookDelivery Object</a>
	</li>
</ul>

<h4 id="get-webhooks">Get Webhooks</h4>

<p>
	<code>GET /webhooks</code>
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = Webhook[]
`}
	language="ts"
/>

<h4 id="create-webhook">Create Webhook</h4>

<p>
	<code>POST /webhooks</code>
</p>

<CodeBlock
	code={`
    type Request = {
        url: string,
        description: string,
        events: string[]
    }
    type Response = Webhook
`}
	language="ts"
/>

<h4 id="update-webhook">Update Webhook</h4>

<p>
	<code>PATCH /webhooks/:id</code>
</p>

<CodeBlock
	code={`
    type Request = {
        url: string,
        description: string,
        events: string[]
    }
    type Response = Webhook
`}
	language="ts"
/>

<h4 id="delete-webhook">Delete Webhook</h4>

<p>
	<code>DELETE /webhooks/:id</code>
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = {}
`}
	language="ts"
/>

<h4 id="get-webhook-deliveries">Get Webhook Deliveries</h4>

<p>
	<code>GET /webhooks/deliveries</code>
</p>

<CodeBlock
	code={`
    type Request = {
        webhookId?: number // Optional. Filter by webhook ID
    }
    type Response = WebhookDelivery[]
`}
	language="ts"
/>

<h3 id="api-keys">API Keys</h3>

<p>Endpoints:</p>

<ul>
	<li>
		<a href="#get-api-keys">GET /api-keys</a> - Get API keys for the project
	</li>
	<li>
		<a href="#create-api-key">POST /api-keys</a> - Create a new API key
	</li>
	<li>
		<a href="#update-api-key">PATCH /api-keys/:id</a> - Update an API key
	</li>
	<li>
		<a href="#delete-api-key">DELETE /api-keys/:id</a> - Delete an API key
	</li>
</ul>

<p>Objects:</p>

<ul>
	<li>
		<a href="#api-key-object">ApiKey Object</a>
	</li>
</ul>

<h4 id="get-api-keys">Get API Keys</h4>

<p>
	<code>GET /api-keys</code> (scope: <strong>api_keys.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = ApiKey[]
`}
	language="ts"
/>

<h4 id="create-api-key">Create API Key</h4>

<p>
	<code>POST /api-keys</code> (scope: <strong>api_keys.write</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        name: string,
        scopes: string[]
    }
    type Response = ApiKey // includes the raw key only on creation
`}
	language="ts"
/>

<h4 id="update-api-key">Update API Key</h4>

<p>
	<code>PATCH /api-keys/:id</code> (scope: <strong>api_keys.write</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        name?: string,
        enabled?: boolean,
        scopes?: string[]
    }
    type Response = ApiKey
`}
	language="ts"
/>

<h4 id="delete-api-key">Delete API Key</h4>

<p>
	<code>DELETE /api-keys/:id</code> (scope: <strong>api_keys.write</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = {}
`}
	language="ts"
/>

<h3 id="suppressions">Suppressions</h3>

<p>Endpoints:</p>

<ul>
	<li>
		<a href="#get-suppressions">GET /suppressions</a> - Get suppressions for the project
	</li>
	<li>
		<a href="#delete-suppression">DELETE /suppressions/:id</a> - Delete a suppression
	</li>
</ul>

<p>Objects:</p>

<ul>
	<li>
		<a href="#suppression-object">Suppression Object</a>
	</li>
</ul>

<h4 id="get-suppressions">Get Suppressions</h4>

<p>
	<code>GET /suppressions</code> (scope: <strong>suppressions.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {
        email?: string, // Optional. Search by email
        reason?: 'bounce' | 'complaint' // Optional. Filter by reason
    }
    type Response = Suppression[]
`}
	language="ts"
/>

<h4 id="delete-suppression">Delete Suppression</h4>

<p>
	<code>DELETE /suppressions/:id</code> (scope: <strong>suppressions.write</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = {}
`}
	language="ts"
/>

<h3 id="analytics">Analytics</h3>

<p>Endpoints:</p>

<ul>
	<li>
		<a href="#get-analytics-stats">GET /analytics/stats</a> - Get analytics statistics
	</li>
	<li>
		<a href="#get-analytics-chart">GET /analytics/sends/chart</a> - Get sends chart data
	</li>
</ul>

<h4 id="get-analytics-stats">Get Analytics Statistics</h4>

<p>
	<code>GET /analytics/stats</code> (scope: <strong>analytics.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = {
        sends_30d: number,
        bounce_rate_30d: number,
        complaint_rate_30d: number
    }
`}
	language="ts"
/>

<h4 id="get-analytics-chart">Get Analytics Chart Data</h4>

<p>
	<code>GET /analytics/sends/chart</code> (scope: <strong>analytics.read</strong>)
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = any // Chart data format
`}
	language="ts"
/>

<h2 id="objects">Objects</h2>

<h3 id="send-object">Send Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface Send = {
	id: number;
	uuid: string;
	created_at: number;
	sent_at: number | null;
	failed_at: number | null;
	status: 'queued' | 'accepted' | 'bounced' | 'complained';
	from_address: string;
	to_address: string;
	subject: string | null;
	body_html: string | null;
	body_text: string | null;
	raw: string;
	attempts: SendAttempt[];
        }
    `}
/>

<h3 id="send-attempt-object">SendAttempt Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface SendAttempt {
	id: number;
	created_at: number;
	status: 'accepted' | 'deferred' | 'bounced';
	try_count: number;
	resolved_mx_hosts: string[];
	accepted_mx_host: string | null;
	smtp_conversations: Record<string, any>;
	error: string | null;
        }
    `}
/>

<h3 id="domain-object">Domain Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface Domain {
	id: number;
	created_at: number;
	domain: string;
	dkim_selector: string;
	dkim_host: string;
	dkim_txt_name: string;
	dkim_public_key: string;
	dkim_txt_value: string;
	dkim_verified: boolean;
	dkim_checked_at: number | null;
	dkim_error_message: string | null;
        }
    `}
/>

<h3 id="webhook-object">Webhook Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface Webhook {
	id: number;
	url: string;
	description: string;
	events: string[];
        }
    `}
/>

<h3 id="webhook-delivery-object">WebhookDelivery Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface WebhookDelivery {
	id: number;
	url: string;
	event: string;
	status: 'pending' | 'delivered' | 'failed';
	response: string;
	created_at: number;
        }
    `}
/>

<h3 id="api-key-object">ApiKey Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface ApiKey {
	id: number;
	name: string;
	scopes: string[];
	key: string | null; // Only included when creating a new key
	created_at: number;
	is_enabled: boolean;
	last_accessed_at: number | null;
        }
    `}
/>

<h3 id="suppression-object">Suppression Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface Suppression {
	id: number;
	created_at: number;
	email: string;
	project: string;
	reason: 'bounce' | 'complaint';
	description: string | null;
        }
    `}
/>
