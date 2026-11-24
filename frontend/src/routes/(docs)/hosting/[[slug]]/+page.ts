import { loadDocsPage } from '@hyvor/design/marketing';
import { getSections } from '$docs/hosting/hosting';

export async function load({ params }: { params: { slug?: string } }) {
	return loadDocsPage({
		basepath: '/hosting',
		rootName: 'Hosting',
		sections: await getSections('en'),
		slug: params.slug ?? ''
	});
}
