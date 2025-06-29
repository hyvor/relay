
import type { Component } from 'svelte';
import Introduction from './content/Introduction.svelte';
import SendEmails from './content/SendEmails.svelte';
import WebhookEvents from './content/WebhookEvents.svelte';
import ConsoleApi from './content/ConsoleApi.svelte';

export const categories: Category[] = [
	{
		name: 'Intro',
		pages: [
			{
				slug: '',
				name: 'Introduction',
                component: Introduction
			}
		]
	},

	{
		name: 'Console API',
		pages: [
			{
				slug: 'api-console',
				name: 'Console API',
				component: ConsoleApi
			},
			{
				slug: 'send-emails',
				name: 'Send Emails',
				component: SendEmails
			}
		]
	},

	{
		name: 'Webhooks',
		pages: [
			{
				slug: 'webhooks',
				name: 'Webhooks Intro',
				component: Introduction
			},
			{
				slug: 'webhooks-events',
				name: 'Events',
				component: WebhookEvents
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
