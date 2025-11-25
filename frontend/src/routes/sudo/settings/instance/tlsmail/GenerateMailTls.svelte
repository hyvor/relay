<script lang="ts">
	import { Button } from '@hyvor/design/components';
	import { generateMailCert } from '../../../sudoActions';

	let generating = $state(false);

	interface Props {
		re?: boolean;
	}

	let { re = false }: Props = $props();

	function handleGenerate() {
		generating = true;
		generateMailCert()
			.then((cert) => {
				alert('Mail TLS Certificate generated successfully.');
			})
			.catch((err) => {
				alert('Error generating Mail TLS Certificate: ' + err.message);
			})
			.finally(() => {
				generating = false;
			});
	}
</script>

<div class="wrap">
	<Button variant="outline" onclick={handleGenerate} disabled={generating}>
		{re ? 'Regenerate' : 'Generate'} Mail TLS Certificate
	</Button>
</div>

<style>
	.wrap {
		margin-top: 15px;
	}
</style>
