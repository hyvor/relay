import type { Component } from 'svelte';
import Introduction from './content/Introduction.svelte';
import Setup from './content/Setup.svelte';
import ProdDeploy from './content/ProdDeploy.svelte';
import Monitoring from './content/monitoring/Monitoring.svelte';
import EasyDeploy from './content/easy/EasyDeploy.svelte';
import Env from './content/Env.svelte';
import Deliverability from './content/Deliverability.svelte';
import Dns from './content/Dns.svelte';
import Scaling from './content/Scaling.svelte';
import EmailProviders from './content/EmailProviders.svelte';
import Telemetry from './content/Telemetry.svelte';

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
				slug: 'deploy-easy',
				name: 'Easy Deploy',
				component: EasyDeploy
			},
			{
				slug: 'deploy',
				name: 'Prod Deploy',
				component: ProdDeploy
			},
			{
				slug: 'setup',
				name: 'Setup',
				component: Setup
			}
		]
	},
	{
		name: 'Advanced',
		pages: [
			{
				slug: 'monitoring',
				name: 'Monitoring',
				component: Monitoring
			},
			{
				slug: 'scaling',
				name: 'Scaling',
				component: Scaling
			},
			{
				slug: 'dns',
				name: 'DNS',
				component: Dns
			}
		]
	},
	{
		name: 'Misc',
		pages: [
			{
				slug: 'deliverability',
				name: 'Deliverability',
				component: Deliverability
			},
			{
				slug: 'providers',
				name: 'Email Providers',
				component: EmailProviders
			},
			{
				slug: 'env',
				name: 'Environment Variables',
				component: Env
			},
			{
				slug: 'telemetry',
				name: 'Telemetry',
				component: Telemetry
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
