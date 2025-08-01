
import type { Component } from 'svelte';
import Introduction from './content/Introduction.svelte';
import Setup from './content/Setup.svelte';
import Deploy from './content/Deploy.svelte';
import Monitoring from './content/Monitoring.svelte';

export const categories: Category[] = [
	{
		name: 'Hosting',
		pages: [
			{
				slug: '',
				name: 'Introduction',
                component: Introduction
			},
			{
				slug: 'deploy',
				name: 'Deploy',
                component: Deploy
			},
			{
				slug: 'setup',
				name: 'Setup',
                component: Setup
			},
			{
				slug: 'scaling',
				name: 'Scaling',
                component: Introduction
			},
			{
				slug: 'monitoring',
				name: 'Monitoring',
				component: Monitoring
			},
			{
				slug: 'api-sudo',
				name: 'Sudo API',
				component: Introduction,
			}
		]
	},
	{
		name: 'Other',
		pages: [
			{
				slug: 'deploy-easy',
				name: 'Easy Deploy',
				component: Introduction,
			},
			{
				slug: 'learn',
				name: 'Learn Sending Emails',
				component: Introduction,
			}
		]
	}
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
