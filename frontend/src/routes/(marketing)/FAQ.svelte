<script>
	import { FAQ } from '@hyvor/design/marketing';

	const items = [
		{
			q: 'Can I self-host for free?',
			a: `Yes. Hyvor Relay is fully open-source under the AGPL-3.0 license. You can deploy and run
			it on your own servers at no cost. We also offer an <strong>Enterprise license</strong>
			for organizations that prefer a commercial license without the obligations of AGPL-3.0, along
			with priority support with SLAs.
			<p>
				Read our <a class="hds-link" href="https://hyvor.com/docs/hosting-license" target="_blank">
					self-hosted licensing
				</a> page for more insights about when to choose an Enterprise license.
			</p>`
		},
		{
			q: 'Does the AGPL version include all features?',
			a: `Yes, both the AGPL and Enterprise versions of Hyvor Relay include the same set of
			features. The only difference is the licensing terms and the support options available.`
		},
		{
			q: 'Will my emails end up in spam?',
			a: `Hyvor Relay is designed from the ground up to ensure high email deliverability rates. We
			implement best practices such as DKIM, SPF, suppression lists, bounce handling, and
			feedback loops to maximize the chances of your emails reaching the inbox and maintaining
			a good sender reputation.
			<ul>
				<li>
					<strong>Automation</strong>: Hyvor Relay automates repetitive tasks such as DNS
					configuration, bounce handling, and feedback loops, reducing the chances of
					human error.
				</li>
				<li>
					<strong>Monitoring</strong>: Hyvor Relay continuously monitors your sending
					domains and IP addresses for deliverability issues and notifies you if any
					problems arise.
				</li>
			</ul>
			<p>
				In addition, Hyvor Relay has extensive documentation that teaches you many
				underlying concepts of SMTP and email deliverability.
			</p>
			<p>
				In short, Hyvor Relay does most of the heavy lifting for you to ensure your emails
				reach the inbox. However, ultimate deliverability also depends on factors such as
				email content, recipient engagement, domain age and reputation, etc., which are
				outside the scope of any email sending service.
			</p>
			<p>
				For more tips on improving email deliverability, check out the <a class="hds-link"
					href="/hosting/deliverability" target="_blank">
					Deliverability
				</a> page.
			</p>`
		},
		{
			q: 'Is Single Sign-On (SSO) supported?',
			a: `Yes. In fact, <strong>SSO is required</strong> in Hyvor Relay. It does not include its own
			login or signup system. Instead, it uses OpenID Connect (OIDC) to handle authentication.`
		},
		{
			q: 'When will Hyvor Relay Cloud open for public access?',
			a: `We are planning to launch Hyvor Relay Cloud to the public in Q1 2026. Stay tuned for
			updates on our website and social media channels. It will be a paid service with a
			competitive usage-based pricing model. <a class="hds-link" href="#waitlist">Join the waitlist</a>.`
		},
		{
			q: 'Will Cloud support both transactional and distributional emails?',
			a: `Hyvor Relay Cloud will only onboard customers sending <strong>transactional emails</strong>
			as a start. We plan to work with developers, indie hackers, small businesses, and enterprises
			for transactional emails. Distributional (marketing) email support may be added in the future
			based on demand.`
		},
		{
			q: 'Can I manage a newsletter with Hyvor Relay?',
			a: `Hyvor Relay is primarily designed as an email sending service and does not include
			built-in newsletter management features. Check out <a class="hds-link" href="https://post.hyvor.com">
				Hyvor Post
			</a>, our newsletter platform that allows you to create, manage, and send newsletters to
			your subscribers. It uses Hyvor Relay under the hood for email delivery.`
		}
	];
</script>

<div class="hds-container">
	<h2>Some questions you might have...</h2>
	<FAQ {items} columns={2} richSchema />
</div>

<style>
	h2 {
		text-align: center;
		font-size: 32px;
		margin-top: 0;
		margin-bottom: 20px;
		padding-top: 60px;
	}
</style>
