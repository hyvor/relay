import sudoApi from './sudoApi';
import type { IpAddress, Queue, Server } from './sudoTypes';

export function getServers() {
	return sudoApi.get<Server[]>({
		endpoint: '/servers'
	});
}

export function getIpAddresses() {
	return sudoApi.get<IpAddress[]>({
		endpoint: '/ip-addresses'
	});
}

export function getQueues() {
	return sudoApi.get<Queue[]>({
		endpoint: '/queues'
	});
}

export function updateServer(serverId: number, data: Partial<Pick<Server, 'api_workers' | 'email_workers' | 'webhook_workers'>>) {
	return sudoApi.patch<Server>({
		endpoint: `/servers/${serverId}`,
		data
	});
}

export function getLogs() {
	return sudoApi.get<string[]>({
		endpoint: '/logs'
	});
}