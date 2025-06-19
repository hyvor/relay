
import type { Component } from 'svelte';
import Introduction from '../content/Introduction.svelte';
import Setup from '../content/Setup.svelte';
export const categories: Category[] = [
	{
		name: 'Hosting',
		pages: [
			{
				slug: '',
				name: 'Install & Run',
                component: Introduction
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
				slug: 'debugging',
				name: 'Debugging',
				component: Introduction
			},
			{
				slug: 'api-sudo',
				name: 'Sudo API',
				component: Introduction,
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
