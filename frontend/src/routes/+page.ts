import { redirect } from '@sveltejs/kit';
import type { PageLoad } from './$types';

// marketing pages are at hyvor.com/relay (see the Caddyfile redirects)
export const load: PageLoad = () => {
	redirect(307, '/console');
};
