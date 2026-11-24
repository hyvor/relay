<script>
	import Scope from '../components/Scope.svelte';
</script>

# Console API

The Console API provides a way to interact with the features of the Console programmatically. It
is also used to <a href="/docs/send-emails">send emails</a>.

To get started, create an API key from the Console. Note that the API key is project-specific,
meaning it can only be used with the project it was created in.

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

```
Authorization: Bearer <your_api_key>
```

</li>
</ul>

<h2 id="scopes">Scopes</h2>

Scopes are used to control access to endpoints of the Console API. When creating an API key, you
can select the scopes that the key will have access to. The available scopes are:

<ul>
<li>
<strong>sends.read</strong>
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
<li>
<strong>org.projects.create</strong>
</li>
</ul>

Each endpoint requires specific scopes to be included in the API key.

<h2 id="rate-limit">Rate Limiting</h2>

The Console API has rate limits to prevent abuse and ensure fair usage.

<ul>
<li>
<strong>Default rate limit</strong>: 100 requests per minute (per API key)
</li>
<li>
<strong><a href="/docs/send-emails">Email sending</a> rate limit</strong>: 10 requests per
second (per project)
</li>
</ul>

If you exceed these limits, you will receive a
<code>429 Too Many Requests</code> response. The following standard headers are included in all API
responses to indicate the current rate limit status:

<ul>
<li>
<code>X-RateLimit-Limit</code>: The maximum number of requests allowed in the current time
window.
</li>
<li>
<code>X-RateLimit-Remaining</code>: The number of requests remaining in the current time
window.
</li>
<li>
<code>X-RateLimit-Reset</code>: How many seconds until the rate limit resets.
</li>
</ul>

<h2 id="errors">Errors</h2>

When a request fails, the Console API responds with a non-2xx HTTP status code and a JSON body
describing the error:

```ts
type ErrorResponse = {
	message: string, // human-readable error message
	status: number, // matches the HTTP response status code
	violations?: { property: string, message: string }[] // present on 422 validation errors
}
```

Examples:

```ts
// 401 Unauthorized
{ message: "Invalid API key.", status: 401 }

// 403 Forbidden
{ message: "Client IP is not allowed for this API key.", status: 403 }

// 404 Not Found
{ message: "Domain not found", status: 404 }

// 422 Unprocessable Entity
{
	message: "domain: This value is not a valid domain.",
	status: 422,
	violations: [
		{ property: "domain", message: "This value is not a valid domain." }
	]
}

// 429 Too Many Requests
{ message: "Rate limit exceeded. Please try again later in 42 seconds.", status: 429 }

// 500 Internal Server Error
{ message: "Internal Server Error. Our team has been notified.", status: 500 }
```

See <a href="#rate-limit">Rate Limiting</a> for more about <code>429 Too Many Requests</code> responses.

<h2 id="endpoints">Endpoints</h2>

<ul>
<li>
<a href="#projects">Projects</a>
</li>
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

<h3 id="projects">Projects</h3>

Endpoints:

<ul>
<li>
<a href="#create-project">POST /projects</a>
<Scope>Organization Endpoint</Scope> - Create a new project
</li>
</ul>

<h4 id="create-project">Create Project</h4>

<code>POST /projects</code> (scope: <strong>org.projects.create</strong>) <Scope>Organization Endpoint</Scope>

```ts
type Request = {
	name: string,
	send_type: 'transactional' | 'distributional'
}
type Response = ProjectUser
```

<h3 id="sends">Sends (Emails)</h3>

Endpoints:

<ul>
<li>
<a href="#send-email">POST /sends</a>
<Scope>Idempotency Supported</Scope> - Send an email
</li>
<li>
<a href="#get-sends">GET /sends</a> - Get sends
</li>
<li>
<a href="#get-send">GET /sends/:id</a> - Get a send by ID
</li>
<li>
<a href="#get-send-uuid">GET /sends/uuid/:uuid</a> - Get a send by UUID
</li>
<li>
<a href="#retry-send">POST /sends/:id/retry</a> - Retry a failed send
</li>
</ul>

Objects:

<ul>
<li>
<a href="#send-object">Send Object</a>
</li>
<li>
<a href="#send-attempt-object">SendAttempt Object</a>
</li>
</ul>

<h4 id="send-email">Send Email</h4>

<code>POST /sends</code> (scope: <strong>sends.send</strong>)

Visit the <a href="/docs/send-emails">Send Emails</a> page for a detailed guide on how to send emails
using this endpoint.

```ts
type Address = string | { email: string, name?: string };
type Request = {
	from: Address,
	to: Address | Address[],
	cc?: Address | Address[],
	bcc?: Address | Address[],
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
```

<h4 id="get-sends">Get Sends</h4>

<code>GET /sends</code> (scope: <strong>sends.read</strong>)

```ts
    type Request = {
        limit?: number, // Optional. Default is 50
        before_id?: number, // Optional. For pagination: pass the id of the last send in the current list
        status?: 'queued' | 'processing' | 'accepted' | 'bounced' | 'complained', // Optional. Filter by status
        from_search?: string, // Optional. Search from address
        to_search?: string // Optional. Search to address
    }
    type Response = Send[]
```

<h4 id="get-send">Get Send by ID</h4>

<code>GET /sends/:id</code> (scope: <strong>sends.read</strong>)

```ts
	type Request = {}
	type Response = Send // includes attempts array
```

<h4 id="get-send-uuid">Get Send by UUID</h4>

<code>GET /sends/uuid/:uuid</code> (scope: <strong>sends.read</strong>)

```ts
    type Request = {}
    type Response = Send // includes attempts array
```

<h4 id="retry-send">Retry Send</h4>

<code>POST /sends/:id/retry</code> (scope: <strong>sends.send</strong>)

Re-queues failed recipients of a send for retry. The send must not already be queued. Only
recipients with a <code>failed</code> status will be retried.

```ts
type Request = {
    send_after?: number // Optional unix timestamp. Defaults to now.
}
type Response = {
    retried_recipients: number // Count of re-queued recipients
}
```

<h3 id="domains">Domains</h3>

Endpoints:

<ul>
<li>
<a href="#get-domains">GET /domains</a> - Get domains of the project
</li>
<li>
<a href="#create-domain">POST /domains</a> - Create a new domain for the project
</li>
<li>
<a href="#verify-domain">POST /domains/verify</a> - Verify a domain
</li>
<li>
<a href="#get-domain">GET /domains/:id</a> - Get a domain by ID or domain name
</li>
<li>
<a href="#delete-domain">DELETE /domains</a> - Delete a domain by ID or domain name
</li>
</ul>

Objects:

<ul>
<li>
<a href="#domain-object">Domain Object</a>
</li>
</ul>

<h4 id="get-domains">Get Domains</h4>

<code>GET /domains</code> (scope: <strong>domains.read</strong>)

```ts
    type Request = {
        limit?: number, // Optional. Default is 50
        offset?: number, // Optional. Default is 0
        search?: string, // Optional. Search by domain name
    }
    type Response = Domain[]
```

<h4 id="create-domain">Create Domain</h4>

<code>POST /domains</code> (scope: <strong>domains.write</strong>)

```ts
type Request = {
    // domain to add
    // unique within the project
	domain: string

	// optional custom DKIM selector.
	// auto-generated if not provided.
	dkim_selector?: string

	// optional custom RSA private key (PEM format).
	// min bits: 1024
	// auto-generated if not provided.
	dkim_private_key?: string
}
type Response = Domain
```

If you set a custom <code>dkim_selector</code>, consider this: one domain can only have one TXT
record per selector in DNS. To avoid first come first serve problem, Hyvor Relay does not check
for the global uniqueness of the selector; it is your responsibility. If you are a platform
provider, it is recommended to use a unique prefix (e.g. <code>myplatform-</code>) or a
timestamp to minimize the risk of conflicts.

<h4 id="verify-domain">Verify Domain</h4>

<code>POST /domains/verify</code> (scope: <strong>domains.write</strong>)

```ts
type Request = {
	// Either id or domain must be provided
	id?: number,
	domain?: string
}
type Response = Domain
```

<h4 id="get-domain">Get Domain</h4>

<code>GET /domains/by</code> (scope: <strong>domains.read</strong>)

```ts
type Request = {
	// Either id or domain must be provided
	id?: number,
	domain?: string
}
type Response = Domain
```

```ts
	type Request = {}
	type Response = Domain
```

<h4 id="delete-domain">Delete Domain</h4>

<code>DELETE /domains</code> (scope: <strong>domains.write</strong>)

```ts
    type Request = {
    	// Either id or domain must be provided
    	id?: number,
    	domain?: string
    }
    type Response = {}
```

<h3 id="webhooks">Webhooks</h3>

Endpoints:

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

Objects:

<ul>
<li>
<a href="#webhook-object">Webhook Object</a>
</li>
<li>
<a href="#webhook-delivery-object">WebhookDelivery Object</a>
</li>
</ul>

<h4 id="get-webhooks">Get Webhooks</h4>

<code>GET /webhooks</code>

```ts
    type Request = {}
    type Response = Webhook[]
```

<h4 id="create-webhook">Create Webhook</h4>

<code>POST /webhooks</code>

See <a href="/docs/webhooks#events">Webhooks</a> page for available events.

```ts
    type Request = {
        url: string,
        description: string,
        events: string[]
    }
    type Response = Webhook
```

<h4 id="update-webhook">Update Webhook</h4>

<code>PATCH /webhooks/:id</code>

```ts
    type Request = {
        url: string,
        description: string,
        events: string[]
    }
    type Response = Webhook
```

<h4 id="delete-webhook">Delete Webhook</h4>

<code>DELETE /webhooks/:id</code>

```ts
    type Request = {}
    type Response = {}
```

<h4 id="get-webhook-deliveries">Get Webhook Deliveries</h4>

<code>GET /webhooks/deliveries</code>

```ts
    type Request = {
        webhook_id?: number // Optional. Filter by webhook ID
    }
    type Response = WebhookDelivery[]
```

<h3 id="api-keys">API Keys</h3>

Endpoints:

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

Objects:

<ul>
<li>
<a href="#api-key-object">ApiKey Object</a>
</li>
</ul>

<h4 id="get-api-keys">Get API Keys</h4>

<code>GET /api-keys</code> (scope: <strong>api_keys.read</strong>)

```ts
    type Request = {}
    type Response = ApiKey[]
```

<h4 id="create-api-key">Create API Key</h4>

<code>POST /api-keys</code> (scope: <strong>api_keys.write</strong>)

Note: Maximum of 10 API keys are allowed per project.

```ts
    type Request = {
        name: string,
        scopes: string[]
    }
    type Response = ApiKey // includes the raw key only on creation
```

<h4 id="update-api-key">Update API Key</h4>

<code>PATCH /api-keys/:id</code> (scope: <strong>api_keys.write</strong>)

```ts
    type Request = {
        name?: string,
        is_enabled?: boolean,
        scopes?: string[]
    }
    type Response = ApiKey
```

<h4 id="delete-api-key">Delete API Key</h4>

<code>DELETE /api-keys/:id</code> (scope: <strong>api_keys.write</strong>)

```ts
    type Request = {}
    type Response = {}
```

<h3 id="suppressions">Suppressions</h3>

Endpoints:

<ul>
<li>
<a href="#get-suppressions">GET /suppressions</a> - Get suppressions for the project
</li>
<li>
<a href="#delete-suppression">DELETE /suppressions/:id</a> - Delete a suppression
</li>
</ul>

Objects:

<ul>
<li>
<a href="#suppression-object">Suppression Object</a>
</li>
</ul>

<h4 id="get-suppressions">Get Suppressions</h4>

<code>GET /suppressions</code> (scope: <strong>suppressions.read</strong>)

```ts
    type Request = {
        email?: string, // Optional. Search by email
        reason?: 'bounce' | 'complaint' // Optional. Filter by reason
    }
    type Response = Suppression[]
```

<h4 id="delete-suppression">Delete Suppression</h4>

<code>DELETE /suppressions/:id</code> (scope: <strong>suppressions.write</strong>)

```ts
    type Request = {}
    type Response = {}
```

<h3 id="analytics">Analytics</h3>

Endpoints:

<ul>
<li>
<a href="#get-analytics-stats">GET /analytics/stats</a> - Get analytics statistics
</li>
<li>
<a href="#get-analytics-chart">GET /analytics/sends/chart</a> - Get sends chart data
</li>
</ul>

<h4 id="get-analytics-stats">Get Analytics Statistics</h4>

<code>GET /analytics/stats</code> (scope: <strong>analytics.read</strong>)

```ts
    type Request = {
        period?: '30d' | '7d' | '24h' // Optional. Default is '30d'
    }
    type Response = {
        sends: number,
        bounce_rate: number,
        complaint_rate: number
    }
```

<h4 id="get-analytics-chart">Get Analytics Chart Data</h4>

<code>GET /analytics/sends/chart</code> (scope: <strong>analytics.read</strong>)

```ts
    type Request = {}
    type Response = any // Chart data format
```

<h2 id="objects">Objects</h2>

<h3 id="send-object">Send Object</h3>

```ts
        interface Send {
	id: number;
	uuid: string;
	created_at: number;
	from_address: string;
	from_name: string | null;
	subject: string | null;
	body_html: string | null;
	body_text: string | null;
	headers: Record<string, string>;
	raw: string;
    queued: boolean;
    send_after: number;
    recipients: SendRecipient[];
	attempts: SendAttempt[];
	feedback: SendFeedback[];
        }
    
```

<h3 id="send-recipient-object">SendRecipient Object</h3>

```ts
        interface SendRecipient {
	id: number;
	type: 'to' | 'cc' | 'bcc';
	address: string;
	name: string;
	status: 'queued' | 'accepted' | 'deferred' | 'bounced' | 'complained' | 'suppressed' | 'failed';
	try_count: number;
        }
    
```

<h3 id="send-attempt-object">SendAttempt Object</h3>

```ts
        interface SendAttempt {
	id: number;
	created_at: number;
	status: 'accepted' | 'deferred' | 'bounced' | 'failed';
	try_count: number;
	resolved_mx_hosts: string[];
	responded_mx_host: string | null;
	smtp_conversations: Record<string, any>;
	recipient_ids = number[];
	duration_ms: number;
	error: string | null;
        }
    
```

<h3 id="bounce-object">Bounce Object</h3>

```ts
        interface Bounce {
    text: string;
    status: string;
        }
    
```

<h3 id="complaint-object">Complaint Object</h3>

```ts
        interface Complaint {
    text: string;
    feedback_type: string;
        }
    
```

<h3 id="domain-object">Domain Object</h3>

```ts
        interface Domain {
	id: number;
	created_at: number;
	domain: string;
	status: 'pending' | 'active' | 'warning' | 'suspended';
	dkim_selector: string;
	dkim_host: string;
	dkim_public_key: string;
	dkim_txt_value: string;
	dkim_checked_at: number | null;
	dkim_error_message: string | null;
        }
    
```

More about <a href="/docs/domains#status">Domain Status</a>.

<h3 id="webhook-object">Webhook Object</h3>

```ts
        interface Webhook {
	id: number;
	url: string;
	description: string | null;
	events: string[];
	secret: string | null;
        }
    
```

<h3 id="webhook-delivery-object">WebhookDelivery Object</h3>

```ts
        interface WebhookDelivery {
	id: number;
	created_at: number;
	url: string;
	event: string;
	status: 'pending' | 'delivered' | 'failed';
	response: string | null;
        }
    
```

<h3 id="api-key-object">ApiKey Object</h3>

```ts
        interface ApiKey {
	id: number;
	name: string;
	scopes: string[];
	key: string | null; // Only included when creating a new key
	created_at: number;
	is_enabled: boolean;
	last_accessed_at: number | null;
        }
    
```

<h3 id="suppression-object">Suppression Object</h3>

```ts
        interface Suppression {
	id: number;
	created_at: number;
	email: string;
	reason: 'bounce' | 'complaint';
	description: string | null;
        }
    
```
