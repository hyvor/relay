import { loadDocsPage } from '@hyvor/design/marketing';
import { SECTIONS } from '../hosting';

export async function load({ params }: { params: { slug?: string } }) {
	return loadDocsPage({
		basepath: '/hosting',
		rootName: 'Hosting',
		sections: SECTIONS,
		slug: params.slug ?? ''
	});
}
