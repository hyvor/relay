<div class="bounces">
	<div class="note">
		<strong>Bounces</strong> refer to email notifications received regarging email delivery,
		usually for failed email deliveries. <strong>FBL</strong> (Feedback Loop) refers to email
		notifications sent by email providers when users mark an email as spam. Parsing these emails
		is complex since some email providers do not follow the standard DSN/ARF formats. Therefore,
		Hyvor Relay logs all bounces and their parse status for 30 days. If you see a bounce/FBL for
		a popular provider that is not parsed correctly, please report it on
		<a href="https://github.com/hyvor/relay/issues/new" class="hds-link" target="_blank"
			>Github</a
		> so we can improve the parsing logic.
	</div>
</div>

<style>
	.bounces {
		padding: 30px 40px;
	}
	.note {
		font-size: 14px;
		color: var(--text-light);
	}
</style>
