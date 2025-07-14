import { error } from '@sveltejs/kit';
import { pages } from '../hosting';

export async function load({ params } : { params: { slug?: string } }) {
	const slug = params.slug;
	const page = slug === undefined ? pages[0] : pages.find((p) => p.slug === slug);

	if (!page) {
		error(404, 'Not found');
	}

	return {
		slug: params.slug,
		name: page.name,
		component: page.component
	};
}
