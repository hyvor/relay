
import type { Component } from 'svelte';
import Introduction from './content/Introduction.svelte';
import SendEmails from './content/SendEmails.svelte';
import Webhooks from './content/Webhooks.svelte';
import ConsoleApi from './content/ConsoleApi.svelte';
import Domains from './content/Domains.svelte';
import SendEmailsSmtp from './content/SendEmailsSmtp.svelte';

export const categories: Category[] = [
	{
		name: 'Intro',
		pages: [
			{
				slug: '',
				name: 'Getting Started',
				component: Introduction
			},
			{
				slug: 'domains',
				name: 'Domains',
				component: Domains
			},
			{
				slug: 'send-emails',
				name: 'Send Emails via API',
				component: SendEmails
			},
			{
				slug: 'send-emails-smtp',
				name: 'Send Emails via SMTP',
				component: SendEmailsSmtp,
			},
		]
	},

	{
		name: 'API',
		pages: [
			{
				slug: 'api-console',
				name: 'Console API',
				component: ConsoleApi
			},
			{
				slug: 'webhooks',
				name: 'Webhooks',
				component: Webhooks
			}
		]
	},
];

export const pages = categories.reduce((acc, category) => acc.concat(category.pages), [] as Page[]);

interface Category {
	name: string;
	pages: Page[];
}

interface Page {
	slug: string;
	name: string;
	component?: Component;
	parent?: string;
}
