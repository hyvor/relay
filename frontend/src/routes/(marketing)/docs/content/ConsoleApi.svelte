<script>
	import { CodeBlock } from '@hyvor/design/components';
</script>

<h1>Console API</h1>

<p>
	The Console API provides a way to interact with the features of the Console programmatically. It
	is also used to send
	<a href="/docs/send-transactional">transactional</a> and
	<a href="/docs/send-distributional">distributional</a> emails.
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
		<strong>Authentication</strong>: Set the <code>Authorization</code> header with your API key as
		a Bearer token:

		<CodeBlock
			code={`
Authorization: Bearer <your_api_key>
`}
			language={null}
		/>
	</li>
</ul>

<h2 id="endpoints">Endpoints</h2>

<ul>
	<li><a href="#emails">Emails</a></li>
	<li><a href="#domains">Domains</a></li>
</ul>

<!-- <h3 id="emails">Emails</h3>

<p>
</p> -->

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
	<code>GET /domains</code>
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
	<code>POST /domains</code>
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
	<code>GET /domains/:id</code>
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
	<code>DELETE /domains/:id</code>
</p>

<CodeBlock
	code={`
    type Request = {}
    type Response = {}
`}
	language="ts"
/>
