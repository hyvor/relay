<script lang="ts">
	import { onMount } from 'svelte';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	export interface CollectedCard {
		stripe_payment_method_id: string;
		brand: string;
		last4: string;
		exp_month: number;
		exp_year: number;
	}

	interface Props {
		onSuccess: (card: CollectedCard) => void;
		onError: (message: string) => void;
	}

	let { onSuccess, onError }: Props = $props();

	let wrap: HTMLDivElement | undefined = $state(undefined);

	// Embeds HYVOR's card-collection component (built in core) the same way
	// other products embed the billing plans component - see
	// core/frontend/static/js/card-component-iframe.js for the counterpart.
	onMount(() => {
		if (!wrap) {
			return;
		}

		const script = document.createElement('script');
		script.src = getAppConfig().hyvor.instance + '/js/card-component-iframe.js';
		// eslint-disable-next-line svelte/no-dom-manipulating -- loading a third-party embed script
		wrap.appendChild(script);

		function handleSuccess(event: Event) {
			const card = (event as CustomEvent).detail?.card as CollectedCard | undefined;
			if (card) {
				onSuccess(card);
			}
		}

		function handleError(event: Event) {
			const message = (event as CustomEvent).detail?.message as string | undefined;
			onError(message || 'Failed to add card. Please try again.');
		}

		window.addEventListener('card-component-iframe-success', handleSuccess);
		window.addEventListener('card-component-iframe-error', handleError);

		return () => {
			window.removeEventListener('card-component-iframe-success', handleSuccess);
			window.removeEventListener('card-component-iframe-error', handleError);
			// eslint-disable-next-line svelte/no-dom-manipulating -- undo the appendChild above
			wrap?.removeChild(script);
		};
	});
</script>

<div class="card-collector" bind:this={wrap}></div>

<style>
	.card-collector {
		min-height: 250px;
	}
</style>
