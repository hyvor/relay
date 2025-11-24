<script>
	import { Accordion } from '@hyvor/design/components';
	import IconCloud from '@hyvor/icons/IconCloud';
	import IconPiggyBank from '@hyvor/icons/IconPiggyBank';
	import IconStar from '@hyvor/icons/IconStar';
	import IconEnvelopeExclamation from '@hyvor/icons/IconEnvelopeExclamation';
	import IconSignpost2 from '@hyvor/icons/IconSignpost2';
	import IconMegaphone from '@hyvor/icons/IconMegaphone';
	import IconDoorOpen from '@hyvor/icons/IconDoorOpen';

	let accordianColorAttrs = {
		headerColor: 'var(--accent-light)',
		openedColor: '#dde8ff',
		borderColor: '#e0eaff',
		topBorderColor: '#e0eaff'
	};
</script>

<div class="wrap">
	<h3 id="faq">Some questions you might have...</h3>

	<div class="faq hds-container">
		<div class="faq-title">Self-Hosting</div>

		<Accordion title="Can I self-host for free?" icon={IconPiggyBank} {...accordianColorAttrs}>
			Yes. Hyvor Relay is fully open-source under the AGPL-3.0 license. You can deploy and run
			it on your own servers at no cost. We also offer an <strong>Enterprise license</strong>
			for organizations that prefer a commercial license without the obligations of AGPL-3.0, along
			with priority support with SLAs.

			<p>
				Read our <a
					class="hds-link"
					href="https://hyvor.com/docs/hosting-license"
					target="_blank"
				>
					self-hosted licensing
				</a> page for more insights about when to choose an Enterprise license.
			</p>
		</Accordion>

		<Accordion
			title="Does the AGPL version include all features?"
			icon={IconStar}
			{...accordianColorAttrs}
		>
			Yes, both the AGPL and Enterprise versions of Hyvor Relay include the same set of
			features. The only difference is the licensing terms and the support options available.
		</Accordion>

		<Accordion
			title="Will my emails end up in spam?"
			icon={IconEnvelopeExclamation}
			{...accordianColorAttrs}
		>
			Hyvor Relay is designed from the ground up to ensure high email deliverability rates. We
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
				In addition, Hyvor Relay has an extensive documentation that teaches you many
				underlying concepts of SMTP and email deliverability.
			</p>

			<p>
				In short, Hyvor Relay does most of the heavy lifting for you to ensure your emails
				reach the inbox. However, ultimate deliverability also depends on factors such as
				email content, recipient engagement, domain age and reputation, etc., which are
				outside the scope of any email sending service.
			</p>

			<p>
				For more tips on improving email deliverability, check out the <a
					class="hds-link"
					href="/hosting/deliverability"
					target="_blank"
				>
					Deliverability
				</a> page.
			</p>
		</Accordion>

		<Accordion
			title="Is Single Sign-On (SSO) supported?"
			icon={IconDoorOpen}
			{...accordianColorAttrs}
		>
			Yes. In fact, <strong>SSO is required</strong> in Hyvor Relay. It does not include its own
			login or signup system. Instead, it uses OpenID Connect (OIDC) to handle authentication.
		</Accordion>

		<div class="faq-title">Cloud</div>

		<Accordion
			title="When will Hyvor Relay Cloud open for public access?"
			icon={IconCloud}
			{...accordianColorAttrs}
		>
			We are planning to launch Hyvor Relay Cloud to the public in Q1 2026. Stay tuned for
			updates on our website and social media channels. It will be a paid service with a
			competitive usage-based pricing model. <a class="hds-link" href="#waitlist"
				>Join the waitlist</a
			>.
		</Accordion>

		<Accordion
			title="Will Cloud support both transactional and distributional emails?"
			icon={IconSignpost2}
			{...accordianColorAttrs}
		>
			Hyvor Relay Cloud will only onboard customers sending <strong
				>transactional emails</strong
			>
			as a start. We plan to work with developers, indiehackers, small businesses, and enterprises
			for transactional emails. Distributional (marketing) email support may be added in the future
			based on demand.
		</Accordion>

		<Accordion
			title="Can I manage a newsletter with Hyvor Relay?"
			icon={IconMegaphone}
			{...accordianColorAttrs}
		>
			Hyvor Relay is primarily designed as an email sending service and does not include
			built-in newsletter management features. Check out <a
				class="hds-link"
				href="https://post.hyvor.com">Hyvor Post</a
			>, our newsletter platform that allows you to create, manage, and send newsletters to
			your subscribers. It uses Hyvor Relay under the hood for email delivery.
		</Accordion>
	</div>
</div>

<style>
	.wrap {
		background-color: white;
		padding: 60px 0 100px;
		margin-top: 60px;
	}

	h3 {
		text-align: center;
		font-size: 32px;
		margin-top: 0;
	}
	.faq {
		gap: 20px;
		max-width: 100%;
		display: flex;
		flex-direction: column;
		justify-content: center;
		margin: 40px auto 0;
		font-size: 18px;
	}

	.faq :global(.accordion-header) {
		font-size: 18px;
	}

	.faq :global(.accordion-header .icon),
	.faq :global(.accordion-header .chevron) {
		color: var(--accent);
	}

	.faq-title {
		font-size: 24px;
		font-weight: 600;
		display: flex;
		align-items: center;
		gap: 10px;
		margin-top: 20px;
		margin-bottom: 10px;
	}

	/*	mobile styles max width 976px */
	@media (max-width: 976px) {
		.faq {
			width: 100%;
			padding: 0 20px;
		}
	}

	h3 {
		text-align: center;
	}
</style>
