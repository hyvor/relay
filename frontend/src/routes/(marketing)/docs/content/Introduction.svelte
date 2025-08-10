<script>
	import { DocsImage } from '@hyvor/design/marketing';
</script>

<h1>Getting Started</h1>

<p>
	Hyvor Relay is an open-source, self-hostable email API for developers. This page will help you
	setting up your account and a project, adding a domain, and sending your first email.
</p>

<h2 id="cloud-vs-self-hosted">Cloud vs Self-hosted</h2>

<p>
	When we say "<strong>Cloud</strong>", we mean the single official instance run by HYVOR at
	<a href="https://relay.hyvor.com">relay.hyvor.com</a>. You need a HYVOR account to log in to
	this instance. At this moment, the cloud instance is not open for public signups.
</p>

<p>
	When we say "<strong>Self-hosted</strong>", we mean any other instance that you or your
	organization has set up. You need the URL and OIDC credentials to log in to a self-hosted
	instance.
</p>

<p>
	This documentation (<a href="/docs">/docs</a>) is for developers who use
	<strong>any instance</strong>
	of Hyvor Relay. If there are any differences between the cloud and self-hosted instances, we
	will mention them in the relevant sections. If you are a system administrator who wants to spin
	up an instance, visit
	<a href="/hosting">/hosting</a>.
</p>

<h2 id="console">Console</h2>

<p>
	Console (<a href="/console">/console</a>) is where you can manage your projects. Upon first
	login, you'll be prompted to create a project to begin.
</p>

<h2 id="project">Project</h2>

<p>
	A project is an organizational unit that contains domains, suppressions, API keys, and other
	settings. One account can have multiple projects. Generally, you would create a project for each
	application you want to send emails from.
</p>

<DocsImage src="/img/docs/intro-new-project.png" alt="Create New Project in Hyvor Relay" />

<p id="transactional-vs-distributional">
	Note that a project can only send either <strong>transactional</strong> or
	<strong>distributional</strong> emails, not both. Each types of emails are sent from different IP
	pools to maintain IP reputation separately.
</p>

<ul>
	<li>
		<strong>Transactional</strong>: These emails are sent to users after they take certain
		actions, like creating an account, resetting a password, or confirming a purchase.
	</li>
	<li>
		<strong>Distributional</strong>: These emails are sent to many people at once, like
		newsletters, product updates, or marketing campaigns.
	</li>
</ul>

<h2 id="domain">Domain</h2>

<p>
	To send emails, you need to add a domain to your project. Go to <strong
		>Console &rarr; Domains</strong
	> and add a domain. You will be asked to add a TXT record to your domain's DNS settings to verify
	ownership.
</p>

<DocsImage src="/img/docs/intro-domain.png" alt="Add Domain in Hyvor Relay" />

<p>
	Visit the <a href="/docs/domains">Domains</a> page to learn more about domains, DKIM, and automating
	domain management via the API.
</p>

<h2 id="send-emails">Send Emails</h2>

<p>
	Once you have a project and a domain, you can start sending emails. Go to <strong
		>Console &rarr; API</strong
	>
	and create an API key with at least the <code>sends.send</code> scope for email sending. Make sure
	to store the key securely, as it will not be shown again.
</p>

<p>
	You are now all set to send emails using the API. Visit the <a href="/docs/send-emails"
		>Send Emails</a
	> page to understand how to send emails using the API.
</p>

<DocsImage src="/img/docs/intro-postman.png" alt="Sending an email via Console API, with Postman" />

<p></p>

<p>
	The <a href="/docs/api-console">Console API</a> page provides more general information on how Hyvor
	Relay's API works and other API endpoints available.
</p>

<h2 id="view-logs">View Logs</h2>

<p>
	At <strong>Console &rarr; Sends</strong>, you can view the logs of the emails you send. The
	overview section shows the key details of the send and also the SMTP conversations between Hyvor
	Relay and the email service provider:
</p>

<DocsImage src="/img/docs/intro-sends.png" alt="View Sends in Hyvor Relay Console" />

<p>You can also view the raw email content in the Raw tab.</p>
