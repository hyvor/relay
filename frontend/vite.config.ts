import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
	plugins: [sveltekit()],
	server: {
		port: 80,
		host: '0.0.0.0',
		allowedHosts: true,
	}
});
