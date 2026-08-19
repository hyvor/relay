import type { NavSectionConfig } from '@hyvor/design/marketing';
import Introduction from './content/Introduction.svelte';
import Setup from './content/Setup.svelte';
import ProdDeploy from './content/ProdDeploy.svelte';
import UpgradeGuide from './content/UpgradeGuide.svelte';
import Monitoring from './content/monitoring/Monitoring.svelte';
import EasyDeploy from './content/easy/EasyDeploy.svelte';
import Env from './content/Env.svelte';
import Deliverability from './content/deliverability/Deliverability.svelte';
import Dns from './content/Dns.svelte';
import Scaling from './content/Scaling.svelte';
import EmailProviders from './content/EmailProviders.svelte';
import HealthChecks from './content/HealthChecks.svelte';

export const SECTIONS: NavSectionConfig[] = [
	{
		name: 'Hosting',
		navs: [
			{
				type: 'page',
				slug: '',
				name: 'Introduction',
				content: Introduction
			},
			{
				type: 'page',
				slug: 'deploy-easy',
				name: 'Easy Deploy',
				content: EasyDeploy
			},
			{
				type: 'page',
				slug: 'deploy',
				name: 'Prod Deploy',
				content: ProdDeploy
			},
			{
				type: 'page',
				slug: 'setup',
				name: 'Setup',
				content: Setup
			},
			{
				type: 'page',
				slug: 'monitoring',
				name: 'Monitoring',
				content: Monitoring
			},
			{
				type: 'page',
				slug: 'scaling',
				name: 'Scaling',
				content: Scaling
			},
			{
				type: 'page',
				slug: 'upgrade',
				name: 'Upgrade Guide',
				content: UpgradeGuide
			}
		]
	},
	{
		name: 'Features',
		navs: [
			{
				type: 'page',
				slug: 'health-checks',
				name: 'Health Checks',
				content: HealthChecks
			},
			{
				type: 'page',
				slug: 'dns',
				name: 'DNS Server',
				content: Dns
			}
		]
	},
	{
		name: 'Misc',
		navs: [
			{
				type: 'page',
				slug: 'deliverability',
				name: 'Deliverability',
				content: Deliverability
			},
			{
				type: 'page',
				slug: 'providers',
				name: 'Email Providers',
				content: EmailProviders
			},
			{
				type: 'page',
				slug: 'env',
				name: 'Environment Variables',
				content: Env
			}
		]
	}
];
