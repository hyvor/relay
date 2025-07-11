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

export function updateIpAddress(ipId: number, data: { queue_id?: number | null; is_active?: boolean }) {
	return sudoApi.patch<IpAddress>({
		endpoint: `/ip-addresses/${ipId}`,
		data
	});
}

export function getLogs() {
	return sudoApi.get<string[]>({
		endpoint: '/logs'
	});
}