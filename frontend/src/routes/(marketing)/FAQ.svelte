<script>
	import { Accordion } from '@hyvor/design/components';
	import IconCloud from '@hyvor/icons/IconCloud';
	import IconPiggyBank from '@hyvor/icons/IconPiggyBank';
	import IconStar from '@hyvor/icons/IconStar';
	import IconEnvelopeExclamation from '@hyvor/icons/IconEnvelopeExclamation';
	import IconCoin from '@hyvor/icons/IconCoin';
	import IconSignpost2 from '@hyvor/icons/IconSignpost2';
	import IconMegaphone from '@hyvor/icons/IconMegaphone';
</script>

<div class="wrap">
	<h3 id="faq">Some questions you might have...</h3>

	<div class="faq hds-container">
		<div class="faq-title">Self-Hosting</div>

		<Accordion title="Can I self-host for free?" icon={IconPiggyBank}>
			Yes! Hyvor Relay is free and open-source software released under the AGPL-3.0 license.
			You can self-host it for free under the terms of the AGPL-3.0 license. For use cases
			where you cannot comply with the AGPL-3.0 license, we offer commercial licenses.
		</Accordion>

		<Accordion title="Does the commercial version include additional features?" icon={IconStar}>
			No, none at all. The commercial version is exactly the same as the open-source version
			in terms of features. The commercial license excempts you from the AGPL-3.0 license's
			obligation to disclose your source code if you modify Hyvor Relay. It also comes with
			commercial support options.
		</Accordion>

		<Accordion title="When should I buy a commercial license?" icon={IconCoin}>
			<!--  -->
		</Accordion>

		<Accordion title="Will my emails end up in spam?" icon={IconEnvelopeExclamation}>
			It's a popular belief that self-hosting an email service is hard, mainly due to the
			complexities of email deliverability. However, we believe this is a misconception born
			out of limitations in existing self-hosted solutions. Hyvor Relay solves this problem
			using two key approaches: <strong>automations</strong> (all possible tasks are
			automated, making it hard to make mistakes) and <strong>monitoring</strong> (if
			something goes wrong, you'll be notified). With these features, Hyvor Relay makes
			self-hosting an email service that keeps your emails out of the spam folder, easy and
			accessible.

			<p>
				In addition, Hyvor Relay has an extensive documentation that teaches you many
				underlying concepts of SMTP and email deliverability.
			</p>
		</Accordion>

		<div class="faq-title">Cloud</div>

		<Accordion title="When will Hyvor Relay Cloud open for public access?" icon={IconCloud}>
			We are planning to launch Hyvor Relay Cloud to the public in Q1 2026. Stay tuned for
			updates on our website and social media channels. It will be a paid service with a
			competitive usage-based pricing model.
		</Accordion>

		<Accordion
			title="Will Cloud support both transactional and distributional emails?"
			icon={IconSignpost2}
		>
			Hyvor Relay Cloud will primarily onboard customers sending <strong
				>transactional emails</strong
			>. We plan to work with developers, indiehackers, small businesses, and enterprises for
			transactional emails.

			<p>
				For <strong>distributional emails</strong> (newsletters, marketing emails, etc.), we
				will only partner with platforms that specialize in such use-cases. For example, newsletter
				platforms, marketing automation tools, CRM software, etc.
			</p>
		</Accordion>

		<Accordion title="Can I manage a newsletter with Hyvor Relay?" icon={IconMegaphone}>
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

	.faq-title {
		font-size: 24px;
		font-weight: 600;
		display: flex;
		align-items: center;
		gap: 10px;
		margin-top: 20px;
		margin-bottom: 10px;
	}
</style>
