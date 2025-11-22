<script lang="ts">
	import { IconMessage, Loader } from '@hyvor/design/components';
	import { getTlsMailCerts } from '../../../sudoActions';
	import type { TlsCertificate } from '../../../sudoTypes';
	import { onMount } from 'svelte';

	let current: null | TlsCertificate = null;
	let latest: null | TlsCertificate = null;

	let loading = $state(true);
	let error = $state('');

	function load() {
		loading = true;
		error = '';

		getTlsMailCerts()
			.then((res) => {
				current = res.current;
				latest = res.latest;
			})
			.catch((err) => {
				error = 'Failed to load TLS mail certificates: ' + err.message;
			})
			.finally(() => {
				loading = false;
			});
	}

	onMount(load);
</script>

{#if loading}
	<Loader />
{:else if error}
	<IconMessage error>{error}</IconMessage>
{:else if current === null && latest === null}
	<div class="none">
		No TLS certificate configured yet. Incoming mail server will not advertise STARTTLS, and
		emails will be sent without encryption.
	</div>
{/if}

<style>
	.none {
		font-size: 14px;
		color: var(--text-light);
	}
</style>
