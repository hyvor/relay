<script lang="ts">
	import { Table, TableRow, Tooltip } from '@hyvor/design/components';

	interface ProviderSmtpResponse {
		name: string;
		url?: string;
		recipient: string[];
		infra: string[];
		other?: Record<string, string>;
	}

	const providerSmtpResponses: ProviderSmtpResponse[] = [
		{
			// https://smtpfieldmanual.com/provider/apple#code_550
			name: 'Apple',
			recipient: ['5.1.1'],
			infra: ['5.7.1'],
			other: {
				'5.2.2': 'over quota'
			}
		},
		{
			// https://smtpfieldmanual.com/provider/google#code_550
			name: 'Gmail (Google)',
			url: 'https://support.google.com/a/answer/3726730?hl=en',
			recipient: ['5.1.1', '5.1.2', '5.2.1'],
			infra: ['5.7.1'],
			other: {
				'5.2.2': 'over quota'
			}
		},
		{
			// https://smtpfieldmanual.com/provider/outlook#code_550
			// https://learn.microsoft.com/en-us/troubleshoot/exchange/email-delivery/ndr/non-delivery-reports-in-exchange-online
			name: 'Outlook (Microsoft)',
			url: 'https://learn.microsoft.com/en-us/troubleshoot/exchange/email-delivery/ndr/non-delivery-reports-in-exchange-online',
			recipient: ['5.1.1', '5.5.0', '5.1.3'],
			infra: ['5.7.1']
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

<p>Below we have listed some of the enhanced SMTP codes used by popular email providers.</p>

<p>
	<strong>Recipient error codes</strong>
	indicate issues with the recipient's email address. If Hyvor Relay encounters these codes, it will
	add the recipient to the suppression list.
</p>

<p>
	<strong>Infrastructure error codes</strong>
	indicate issues with Hyvor Relay's sending infrastructure (e.g., an IP address being blocked). Those
	codes are serious and may impact email deliverability for multiple recipients. A remedy must be applied
	by the administrator of the Hyvor Relay instance. Responses are logged and can be viewed in the
	<strong>Sudo &rarr; Debug &rarr; Infrastructure Bounces</strong>
	section. There is also a sudo health check to alert if infrastructure bounces are detected.
</p>

<Table columns="1fr 1fr 1fr 1fr">
	<TableRow head>
		<div>Provider</div>
		<div>Recipient</div>
		<div>Infrastructure</div>
		<div>Other</div>
	</TableRow>
	{#each providerSmtpResponses as response}
		<TableRow>
			<div>{response.name}</div>
			<div>
				{#each response.recipient as code, index}
					<code>{code}</code>{index < response.recipient.length - 1 ? ', ' : ''}
				{/each}
			</div>
			<div>
				{#each response.infra as code, index}
					<code>{code}</code>{index < response.infra.length - 1 ? ', ' : ''}
				{/each}
			</div>
			<div>
				{#if response.other}
					{#each Object.entries(response.other) as [code, description], index}
						<Tooltip text={description}>
							<code>{code}</code>
						</Tooltip>
						{index < Object.entries(response.other).length - 1 ? ', ' : ''}
					{/each}
				{:else}
					—
				{/if}
			</div>
		</TableRow>
	{/each}
</Table>

<p>
	Hyvor Relay does not suppress recipients if an enhanced SMTP code is not set in the response
	(e.g., Yahoo returns a generic 550 without an enhanced code).
</p>
