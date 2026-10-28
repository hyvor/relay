import { loadDocsPage } from '@hyvor/design/marketing';
import { SECTIONS } from '../docs';

export async function load({ params }: { params: { slug?: string } }) {
	return loadDocsPage({
		basepath: '/docs',
		sections: SECTIONS,
		slug: params.slug ?? ''
	});
}
