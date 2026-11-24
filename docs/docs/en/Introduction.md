<script>
	import imgIntroNewProject from '../images/intro-new-project.png';
	import imgIntroDomain from '../images/intro-domain.png';
	import imgIntroPostman from '../images/intro-postman.png';
	import imgIntroSends from '../images/intro-sends.png';
	import { DocsImage } from '@hyvor/design/marketing';
</script>

# Getting Started

Hyvor Relay is an open-source, self-hostable email API designed for developers. This page will
guide you through setting up your account and project, adding a domain, and sending your first
email.

<h2 id="cloud-vs-self-hosted">Cloud vs Self-hosted</h2>

When we say "<strong>Cloud</strong>", we mean the single official instance run by HYVOR at
<a href="https://relay.hyvor.com">relay.hyvor.com</a>. You need a HYVOR account to log in to
this instance. At this moment, the cloud instance is not open for public signups.

When we say "<strong>Self-hosted</strong>", we mean any other instance that you or your
organization has set up. You need the URL and OIDC credentials to log in to a self-hosted
instance.

This documentation (<a href="/docs">/docs</a>) is intended for developers working with
<strong>any Hyvor Relay instance</strong>. Whenever relevant, we will note any differences
between the Cloud and self-hosted deployments. If you are a system administrator who wants to
spin up an instance, visit
<a href="/hosting">/hosting</a>.

<h2 id="console">Console</h2>

Console (<a href="/console">/console</a>) is where you can manage your projects. Upon first
login, you'll be prompted to create a project to begin.

<h2 id="project">Project</h2>

A project is an organizational unit that contains domains, suppressions, API keys, and other
settings. One account can have multiple projects. Generally, you would create a project for each
application you want to send emails from.

<DocsImage src={imgIntroNewProject} alt="Create New Project in Hyvor Relay" />

<p id="transactional-vs-distributional">
Note that a project can only send either <strong>transactional</strong> or
<strong>distributional</strong> emails, not both. Each type of email is sent from different IP
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

To send emails, you need to add a domain to your project. Go to <strong>Console &rarr; Domains</strong> and add a domain. You will be asked to add a TXT record to your domain's DNS settings to verify
ownership.

<DocsImage src={imgIntroDomain} alt="Add Domain in Hyvor Relay" />

Visit the <a href="/docs/domains">Domains</a> page to learn more about domains, DKIM, and automating
domain management via the API.

<h2 id="send-emails">Send Emails</h2>

Once you have a project and a domain, you can start sending emails. Go to <strong>Console &rarr; API</strong>
and create an API key with at least the <code>sends.send</code> scope for email sending. Make sure
to store the key securely, as it will not be shown again.

You are now all set to send emails using the API. Visit the <a href="/docs/send-emails">Send Emails</a> page for more details.

<DocsImage src={imgIntroPostman} alt="Sending an email via Console API, with Postman" />

The <a href="/docs/api-console">Console API</a> page provides more general information on how Hyvor
Relay's API works and other API endpoints available.

<h2 id="view-logs">View Logs</h2>

At <strong>Console &rarr; Sends</strong>, you can view the logs of the emails you send. The
overview section shows the key details of the send and also the SMTP conversations between Hyvor
Relay and the email service provider:

<DocsImage src={imgIntroSends} alt="View Sends in Hyvor Relay Console" />

You can also view the raw email content in the Raw tab.
