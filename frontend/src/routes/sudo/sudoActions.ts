import sudoApi from './sudoApi';
import { instanceStore, serversStore } from './sudoStore';
import type { IpAddress, Queue, Server, SudoInitResponse, HealthCheckResults, Instance, DnsRecord, DnsRecordType } from './sudoTypes';

export function initSudo() {
	return sudoApi.post<SudoInitResponse>({
		endpoint: '/init'
	})
}

export async function updateInstance(updates: { domain?: string, private_network_cidr?: string }) {
	const response = await sudoApi.patch<Instance>({
		endpoint: '/instance',
		data: updates
	});

	instanceStore.set(response);

	return response;
}

export function getServers() {
	return sudoApi.get<Server[]>({
		endpoint: '/servers'
	});
}

export async function updateServer(serverId: number, updates: Partial<Server>) {
	const response = await sudoApi.patch<Server>({
		endpoint: `/servers/${serverId}`,
		data: updates
	});

	serversStore.update(servers => servers.map(server => server.id === serverId ? response : server));

	return response;
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

export function getDnsRecords() {
	return sudoApi.get<DnsRecord[]>({
		endpoint: '/dns-records'
	});
}

export function createDnsRecord(record: {
	type: DnsRecordType;
	subdomain: string;
	content: string;
	ttl: number;
	priority: number;
}) {
	return sudoApi.post<DnsRecord>({
		endpoint: '/dns-records',
		data: record
	});
}

export function updateDnsRecord(recordId: number, record: {
	type?: DnsRecordType;
	subdomain?: string;
	content?: string;
	ttl?: number;
	priority?: number;
}) {
	return sudoApi.patch<DnsRecord>({
		endpoint: `/dns-records/${recordId}`,
		data: record
	});
}

export function deleteDnsRecord(recordId: number) {
	return sudoApi.delete({
		endpoint: `/dns-records/${recordId}`
	});
}

export function debugParseBounceFBL(raw: string, type: 'bounce' | 'fbl') {
	return sudoApi.post<{parsed: Record<string, any>}>({
		endpoint: '/debug/parse-bounce-fbl',
		data: { raw, type }
	});
}