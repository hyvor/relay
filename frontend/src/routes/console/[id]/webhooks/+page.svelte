<script lang="ts">
	import { 
		Button, 
		toast, 
		confirm, 
		TabNav, 
		TabNavItem, 
		Loader
	} from '@hyvor/design/components';
	import IconPlus from '@hyvor/icons/IconPlus';
	import SingleBox from '../../@components/content/SingleBox.svelte';
	import WebhookModal from './WebhookModal.svelte';
	import WebhookList from './WebhookList.svelte';
	import WebhookDeliveryList from './WebhookDeliveryList.svelte';
	import type { Webhook, WebhookDelivery } from '../../types';
	import {
		getWebhooks,
		deleteWebhook,
		getWebhookDeliveries
	} from '../../lib/actions/webhookActions';
	import { onMount } from 'svelte';
	import { cant, redirectIfCant } from '../../lib/scope.svelte';

	let webhooks: Webhook[] = $state([]);
	let deliveries: WebhookDelivery[] = $state([]);
	let loading = $state(true);
	let deliveriesLoading = $state(false);
	let showModal = $state(false);
	let editingWebhook: Webhook | null = $state(null);
	let activeTab = $state<'configure' | 'deliveries'>('configure');

	onMount(() => {
		loadWebhooks();
	});

	$effect(() => {
		if (activeTab === 'deliveries') {
			loadDeliveries();
		}
	});

	function loadWebhooks() {
		loading = true;
		getWebhooks()
			.then((webhookList) => {
				webhooks = webhookList;
			})
			.catch((error) => {
				toast.error('Failed to load webhooks: ' + error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	function loadDeliveries() {
		deliveriesLoading = true;
		getWebhookDeliveries()
			.then((deliveryList) => {
				deliveries = deliveryList;
			})
			.catch((error) => {
				console.error('Failed to load webhook deliveries:', error);
				toast.error('Failed to load webhook deliveries');
			})
			.finally(() => {
				deliveriesLoading = false;
			});
	}

	function handleWebhookSaved(webhook: Webhook) {
		loadWebhooks();
	}

	function handleCreateWebhook() {
		editingWebhook = null;
		showModal = true;
	}

	function handleEditWebhook(webhook: Webhook) {
		editingWebhook = webhook;
		showModal = true;
	}

	async function handleDeleteWebhook(webhook: Webhook) {
		const confirmed = await confirm({
			title: 'Delete webhook',
			content: `Are you sure you want to delete the webhook "${webhook.url}"?`,
			confirmText: 'Delete',
			cancelText: 'Cancel',
			danger: true
		});

		if (confirmed) {
			deleteWebhook(webhook.id)
				.then(() => {
					loadWebhooks();
					toast.success('Webhook deleted');
				})
				.catch((error) => {
					console.error('Failed to delete webhook:', error);
					toast.error('Failed to delete webhook');
				});
		}
	}

	onMount(() => redirectIfCant('webhooks.read'));
</script>

<SingleBox>
	<div class="top">
		<div class="tabs">
			<TabNav bind:active={activeTab}>
				<TabNavItem name="configure">Configure</TabNavItem>
				<TabNavItem name="deliveries">Deliveries</TabNavItem>
			</TabNav>
		</div>
		{#if activeTab === 'configure'}
			<Button variant="fill" on:click={handleCreateWebhook} disabled={cant('webhooks.write')}>
				<IconPlus size={16} />
				Create Webhook
			</Button>
		{/if}
	</div>

	<div class="content">
		{#if activeTab === 'configure'}
			{#if loading}
				<div class="loader-container">
					<Loader />
				</div>
			{:else}
				<WebhookList {webhooks} onEdit={handleEditWebhook} onDelete={handleDeleteWebhook} />
			{/if}
		{:else if activeTab === 'deliveries'}
			{#if deliveriesLoading}
				<div class="loader-container">
					<Loader />
				</div>
			{:else}
				<WebhookDeliveryList {deliveries} />
			{/if}
		{/if}
	</div>
</SingleBox>

<WebhookModal
	bind:show={showModal}
	bind:webhook={editingWebhook}
	onWebhookSaved={handleWebhookSaved}
/>


<style>
	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px 30px;
		border-bottom: 1px solid var(--border);
	}

	.tabs {
		flex: 1;
	}

	.content {
		padding: 30px;
		flex: 1;
		display: flex;
		flex-direction: column;
		overflow: auto;
	}

	.loader-container {
		display: flex;
		justify-content: center;
		align-items: center;
		flex: 1;
	}

</style>
