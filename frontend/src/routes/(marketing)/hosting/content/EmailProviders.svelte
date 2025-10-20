<script lang="ts">
	interface SmtpResponse {
		code: number;
		enhancedCode: string;
		other?: string;
	}

	interface SmtpResponses {
		name: string;
		suppression: SmtpResponse[];
	}

	const smtpResponses: SmtpResponses = [
		{
			name: 'Apple',
			suppression: [
				{ code: 550, enhancedCode: '5.1.1' },
				{ code: 550, enhancedCode: '5.2.1' }
			]
		}
	];
</script>

<h1>Email Providers</h1>

<p>
	While SMTP is a standardized protocol, different email providers implement it in slightly
	different ways. Also, feedback loops (FBLs) are not standardized, and each email provider has
	its own way of reporting spam complaints. This document outlines notable differences among
	popular email providers and how Hyvor Relay handles them.
</p>

<ul>
	<li>
		<a href="#fbls">Feedback Loops (FBLs)</a>
	</li>
	<li>
		<a href="#smtp-responses">SMTP Responses</a>
	</li>
</ul>

<h2 id="fbls">Feedback Loops (FBLs)</h2>

<p>
	When a recipient marks an email as spam, the email provider may send a complaint to the sender
	via a feedback loop (FBL). When hosting Hyvor Relay, you may sign up for these FBLs to monitor
	spam complaints and route FBL reports <code>abuse@</code>
	and <code>fbl@</code> emails of your
	<a href="/hosting/setup#instance-domain">instance domain</a>. Hyvor Relay's incoming SMTP server
	will process these reports and automatically add the recipients to the suppression list.
</p>

<h3 id="yahoo-fbl">Yahoo FBL</h3>

<p>
	<!--  -->
</p>

<p>
	<!--  -->
</p>

<h2 id="smtp-responses">SMTP Responses</h2>

<p>
	Here is how Hyvor Relay handles SMTP responses from email providers for suppressions and other
	errors:
</p>

<p>
	<!--  -->
</p>
