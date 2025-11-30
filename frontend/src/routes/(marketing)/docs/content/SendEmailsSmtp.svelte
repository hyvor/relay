<script lang="ts">
	import { Table, TableRow, TabNav, TabNavItem } from '@hyvor/design/components';
	import { DocsImage } from '@hyvor/design/marketing';

	let active: 'cloud' | 'self-hosted' = 'cloud';
</script>

<h1>Send Emails via SMTP</h1>

<p>
	In addition to <a href="/docs/send-emails">sending emails via API</a>, Hyvor Relay also supports
	sending emails using the <strong>SMTP protocol</strong>.
</p>

<h2 id="smtp-vs-api">SMTP vs API</h2>

<p>
	<strong> Pros of using SMTP: </strong>
</p>

<ul>
	<li>Easier integration with existing systems.</li>
	<li>Wide compatibility with various programming languages.</li>
	<li>Easier to switch email service providers in the future.</li>
</ul>

<p>
	<strong> Cons of using SMTP: </strong>
</p>

<ul>
	<li>Potentially slower performance.</li>
	<li>More complex error handling and reporting.</li>
	<li>
		<a href="/docs/send-emails#idempotency">Idempotency</a> is not supported.
	</li>
</ul>

<h2 id="smtp-setup">Setting Up SMTP</h2>

<p>
	First, make sure to <a href="/docs#project">set up a project</a> and generate an API key with
	the <strong>sends.send</strong> scope enabled.
</p>

<p>Then, use the following SMTP configuration:</p>

<TabNav bind:active>
	<TabNavItem name="cloud">Cloud</TabNavItem>
	<TabNavItem name="self-hosted">Self-Hosted</TabNavItem>
</TabNav>

<ul>
	<li>
		<strong>SMTP Server:</strong>

		{#if active === 'cloud'}
			<code>mx.mail.hyvor-relay.com</code>
		{:else}
			<code>mx.mail.relay.yourdomain.com</code> <br /> (replace with
			<a href="/hosting/setup#instance-domain">your instance domain</a>)
		{/if}
	</li>
	<li><strong>Port:</strong> <code>587</code> (recommended) or <code>25</code></li>
	<li><strong>Username:</strong> <code>relay</code> (any username will work)</li>
	<li><strong>Password:</strong> Your API Key</li>
	<li>
		<strong>Encryption:</strong> <code>TLS</code> recommended if supported by your SMTP client
	</li>
</ul>

<hr style="margin:60px 0;" />

<p>Below we have provided some internal details on how Hyvor Relay's SMTP integration works.</p>

<h2 id="how-it-works">How It Works</h2>

<p>
	Hyvor Relay's SMTP server, which listens on port 587 (or port 25), simply acts as an interface
	that converts your email to an API request and forwards it to the Hyvor Relay email sending API.
</p>

<DocsImage src="/img/docs/smtp-arch.svg" alt="SMTP Architecture Diagram" width={450} />

<p>
	In other words, when you send an email via SMTP, you are still ultimately using the API
	indirectly.
</p>

<h2 id="mime-to-api-mapping">MIME to API Mapping</h2>

<p>
	The SMTP server parses the incoming MIME email and maps its components to the corresponding
	<a href="/docs/send-emails#sending">API request fields</a> as follows:
</p>

<Table columns="1fr 1fr">
	<TableRow head>
		<td><strong>MIME Component</strong></td>
		<td><strong>API Field</strong></td>
	</TableRow>
	<TableRow>
		<td>
			<code>From</code> header (top-level)
		</td>
		<td>
			<code>from</code>
		</td>
	</TableRow>
	<TableRow>
		<td>
			<code>To</code>, <code>Cc</code>, <code>Bcc</code> headers (top-level)
		</td>
		<td>
			<code>to</code>, <code>cc</code>, <code>bcc</code>
		</td>
	</TableRow>
	<TableRow>
		<td>
			<code>Subject</code> header (top-level)
		</td>
		<td>
			<code>subject</code>
		</td>
	</TableRow>
	<TableRow>
		<td>
			First occurring <code>text/plain</code> part
		</td>
		<td>
			<code>text</code>
		</td>
	</TableRow>
	<TableRow>
		<td>
			First occurring <code>text/html</code> part
		</td>
		<td>
			<code>html</code>
		</td>
	</TableRow>
	<TableRow>
		<td>
			Other top-level headers not listed <a href="/docs/send-emails#custom-headers-limit"
				>here</a
			>
		</td>
		<td>
			<code>headers</code>
		</td>
	</TableRow>
	<TableRow>
		<td>
			All other parts with a <code>Content-Disposition: attachment</code> header
		</td>
		<td>
			<code>attachments</code>
		</td>
	</TableRow>
</Table>
