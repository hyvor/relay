<script lang="ts">
	import { 
		Button, 
		toast, 
		confirm, 
		TabNav, 
		TabNavItem, 
		Loader,
		Modal,
		TextInput,
		SplitControl,
		IconButton,
		Tag
	} from '@hyvor/design/components';
	import IconPlus from '@hyvor/icons/IconPlus';
	import IconCopy from '@hyvor/icons/IconCopy';
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
	import { copyAndToast } from '../../lib/helpers/copy';

	let webhooks: Webhook[] = $state([]);
	let deliveries: WebhookDelivery[] = $state([]);
	let loading = $state(true);
	let deliveriesLoading = $state(false);
	let showModal = $state(false);
	let showWebhookSecretModal = $state(false);
	let newWebhook: Webhook | null = $state(null);
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
		if (webhook.key) {
			newWebhook = webhook;
			showWebhookSecretModal = true;
		}
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

{#if showWebhookSecretModal && newWebhook}
	<Modal
		title="Your New Webhook Secret"
		bind:show={showWebhookSecretModal}
		size="medium"
		footer={{
			cancel: {
				text: 'Close'
			},
			confirm: false
		}}
	>
		<div class="modal-content">
			<div class="warning-box">
				<strong>Important:</strong> This is the only time you'll see this webhook secret. Make sure
				to copy it and store it securely.
			</div>

			<SplitControl label="Webhook Secret">
				<div class="key-input-group">
					<TextInput value={newWebhook.key || ''} readonly block />
					<IconButton
						size="small"
						color="input"
						style="margin-left:4px;"
						on:click={() => copyAndToast(newWebhook?.key || '', 'Webhook secret copied')}
					>
						<IconCopy size={12} />
					</IconButton>
				</div>
			</SplitControl>

			<SplitControl label="URL">
				<span>{newWebhook.url}</span>
			</SplitControl>

			<SplitControl label="Description">
				<span>{newWebhook.description || 'No description'}</span>
			</SplitControl>

			<SplitControl label="Events">
				<div class="events-display">
					{#each newWebhook.events as event}
						<Tag size="small">
							{event}
						</Tag>
					{/each}
				</div>
			</SplitControl>
		</div>
	</Modal>
{/if}

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

	.modal-content {
		padding: 20px 0;
	}

	.warning-box {
		padding: 16px;
		background: var(--orange-50);
		border: 1px solid var(--orange-200);
		border-radius: 6px;
		color: var(--orange-900);
		margin-bottom: 20px;
	}

	.key-input-group {
		display: flex;
		gap: 8px;
		align-items: flex-end;
	}

	.events-display {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
	}
</style>
