<script>
	import { DocsImage } from '@hyvor/design/marketing';
</script>

<h1>Getting Started</h1>

<p>
	Hyvor Relay is an open-source, self-hostable, email API for developers. This page will help you
	setting up your account and a project, adding a domain, and sending your first email. For
	self-hosting, see <a href="/hosting">Hosting</a> docs.
</p>

<h2 id="console">Console</h2>

<p>
	Console (<a href="/console">/console</a>) is where you can manage your projects. To access it,
	sign in using your credentials via your OIDC authentication provider. Upon first login, you'll
	be prompted to create a project to begin.
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

<!--  -->
