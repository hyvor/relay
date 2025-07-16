import sudoApi from './sudoApi';
import type { IpAddress, Queue, Server, HealthCheckResults } from './sudoTypes';

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


export function getLogs() {
	return sudoApi.get<string[]>({
		endpoint: '/logs'
	});
}

export function getHealthChecks() {
	return sudoApi.get<HealthCheckResults>({
		endpoint: '/health-checks'
	});
}

export function runHealthChecks() {
	return sudoApi.post<HealthCheckResults>({
		endpoint: '/health-checks'
	});
}