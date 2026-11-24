import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';
import { markdownPlugin } from '@hyvor/design/dev';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	extensions: ['.svelte', '.md'],

	// Consult https://svelte.dev/docs/kit/integrations
	// for more information about preprocessors
	preprocess: [vitePreprocess(), markdownPlugin()],

	kit: {
		// adapter-auto only supports some environments, see https://svelte.dev/docs/kit/adapter-auto for a list.
		// If your environment is not supported, or you settled on a specific environment, switch out the adapter.
		// See https://svelte.dev/docs/kit/adapters for more information about adapters.
		adapter: adapter({
			fallback: 'fallback.html'
		}),
		alias: {
			// docs are kept in the repo root, and synced to hyvor/marketing
			$docs: '../docs'
		},
		typescript: {
			config(config) {
				// ../docs has no node_modules, resolve its types from here
				config.compilerOptions.paths['@hyvor/design/marketing'] = [
					'../node_modules/@hyvor/design/dist/marketing/index.d.ts'
				];
				config.include.push('../../docs/**/*.ts');
			}
		}
	},

	compilerOptions: {
		experimental: {
			async: true
		}
	}
};

export default config;
