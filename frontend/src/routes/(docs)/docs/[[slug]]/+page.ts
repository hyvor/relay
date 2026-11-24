import { loadDocsPage } from '@hyvor/design/marketing';
import { getSections } from '$docs/docs/docs';

export async function load({ params }: { params: { slug?: string } }) {
	return loadDocsPage({
		basepath: '/docs',
		rootName: 'Docs',
		sections: await getSections('en'),
		slug: params.slug ?? ''
	});
}
