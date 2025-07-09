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
		<strong>suppressions.write</strong>
	</li>
	<li>
		<strong>suppressions.read</strong>
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
		<!-- <a href="#webhooks">Webhooks</a> -->
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

<h4 id="send-email">Send Email</h4>
<h4 id="get-sends">Get Sends</h4>
<h4 id="get-send">Get Send</h4>

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

<ul></ul>

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

<h2 id="objects">Objects</h2>
<h3 id="domain-object">Domain Object</h3>

<CodeBlock
	language="ts"
	code={`
        interface Domain = {
	id: number;
	created_at: number;
	domain: string;
	dkim_selector: string;
	dkim_host: string;
	dkim_txt_name: string;
	dkim_public_key: string;
	dkim_txt_value: string;
	dkim_verified: boolean;
	dkim_checked_at?: number;
	dkim_error_message?: string;
        }
    `}
/>