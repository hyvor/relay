import type { Webhook, WebhookDelivery } from "../../types";
import consoleApi from "../consoleApi.svelte";

export function getWebhooks() {
	return consoleApi.get<Webhook[]>({
		endpoint: 'webhooks'
	});
}

export function createWebhook(url: string, description: string, events: string[]) {
	return consoleApi.post<Webhook>({
		endpoint: 'webhooks',
		data: {
			url,
			description,
			events
		}
	});
}

export function updateWebhook(id: number, url: string, description: string, events: string[]) {
	return consoleApi.patch<Webhook>({
		endpoint: `webhooks/${id}`,
		data: {
			url,
			description,
			events
		}
	});
}

export function deleteWebhook(id: number) {
	return consoleApi.delete<void>({
		endpoint: `webhooks/${id}`
	});
}

export function getWebhookDeliveries(webhookId?: number) {
	return consoleApi.get<WebhookDelivery[]>({
		endpoint: 'webhooks/deliveries',
		data: webhookId ? { webhookId } : {}
	});
} 