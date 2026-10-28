import type { NavSectionConfig } from '@hyvor/design/marketing';
import Introduction from './content/Introduction.svelte';
import SendEmails from './content/SendEmails.svelte';
import Webhooks from './content/Webhooks.svelte';
import ConsoleApi from './content/ConsoleApi.svelte';
import Domains from './content/Domains.svelte';
import SendEmailsSmtp from './content/SendEmailsSmtp.svelte';

export const SECTIONS: NavSectionConfig[] = [
	{
		name: '',
		navs: [
			{
				type: 'page',
				slug: '',
				name: 'Getting Started',
				content: Introduction
			},
			{
				type: 'page',
				slug: 'domains',
				name: 'Domains',
				content: Domains
			},
			{
				type: 'page',
				slug: 'send-emails',
				name: 'Send Emails via API',
				content: SendEmails
			},
			{
				type: 'page',
				slug: 'send-emails-smtp',
				name: 'Send Emails via SMTP',
				content: SendEmailsSmtp
			}
		]
	},

	{
		name: 'API',
		navs: [
			{
				type: 'page',
				slug: 'api-console',
				name: 'Console API',
				content: ConsoleApi
			},
			{
				type: 'page',
				slug: 'webhooks',
				name: 'Webhooks',
				content: Webhooks
			}
		]
	}
];
