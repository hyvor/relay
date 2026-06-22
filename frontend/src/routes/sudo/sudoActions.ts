import sudoApi from './sudoApi';
import { serversStore } from './sudoStore';
import type { Send, SendRecipientStatus } from '../console/types';
import type {
	IpAddress,
	Queue,
	Server,
	SudoInitResponse,
	HealthCheckResults,
	DnsRecord,
	DefaultDnsRecord,
	DnsRecordType,
	DebugIncomingEmail,
	InfrastructureBounce,
	TlsCertificate,
	Organization,
	SudoProjectsResponse,
	SudoProjectResponse,
	SudoSendsResponse,
	SudoSendResponse
} from './sudoTypes';

export function initSudo() {
	return sudoApi.post<SudoInitResponse>({
		endpoint: '/init'
	});
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

	serversStore.update((servers) =>
		servers.map((server) => (server.id === serverId ? response : server))
	);

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

export function updateIpAddress(ipId: number, data: { queue_id?: number | null }) {
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

export function getDefaultDnsRecords() {
	return sudoApi.get<DefaultDnsRecord[]>({
		endpoint: '/dns-records/default'
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

export function updateDnsRecord(
	recordId: number,
	record: {
		type?: DnsRecordType;
		subdomain?: string;
		content?: string;
		ttl?: number;
		priority?: number;
	}
) {
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

export function debugGetIncomingMails(limit: number = 20, offset: number = 0) {
	return sudoApi.get<DebugIncomingEmail[]>({
		endpoint: '/debug/incoming-mails',
		data: { limit, offset }
	});
}

export function debugParseBounceFBL(raw: string, type: 'bounce' | 'complaint') {
	return sudoApi.post<{ parsed: Record<string, any> }>({
		endpoint: '/debug/parse-bounce-fbl',
		data: { raw, type }
	});
}

export function getInfrastructureBounces(limit: number = 20, offset: number = 0, isRead?: boolean) {
	const data: Record<string, any> = { limit, offset };
	if (isRead !== undefined) {
		data.is_read = isRead;
	}
	return sudoApi.get<InfrastructureBounce[]>({
		endpoint: '/infrastructure-bounces',
		data
	});
}

export function markInfrastructureBounceAsRead(id: number) {
	return sudoApi.patch<InfrastructureBounce>({
		endpoint: `/infrastructure-bounces/${id}/mark-as-read`
	});
}

export function markAllInfrastructureBouncesAsRead() {
	return sudoApi.post<{ marked_count: number }>({
		endpoint: '/infrastructure-bounces/mark-all-as-read'
	});
}

export function getTlsMailCerts() {
	return sudoApi.get<{ current: TlsCertificate | null; latest: TlsCertificate | null }>({
		endpoint: '/tls/mail-certs'
	});
}

export function generateMailCert() {
	return sudoApi.post<TlsCertificate>({
		endpoint: '/tls/mail-certs/generate'
	});
}

export function getSends(opts: {
	project_id: number | null;
	status: SendRecipientStatus | null;
	from_search: string | null;
	to_search: string | null;
	subject_search: string | null;
	date_from_search: string | null;
	date_to_search: string | null;
	limit: number;
	before_id: number | null;
}) {
	return sudoApi
		.get<SudoSendsResponse>({
			endpoint: '/sends',
			data: opts
		})
		.then(({ sends, projects }) => {
			const projectsById = new Map(projects.map((project) => [project.id, project]));

			return sends.map(({ project_id, ...send }) => ({
				...send,
				project: projectsById.get(project_id) ?? { id: project_id, name: 'Unknown project' }
			}));
		});
}

export function getSendByUuid(uuid: string) {
	return sudoApi
		.get<SudoSendResponse>({
			endpoint: `/sends/uuid/${uuid}`
		})
		.then(({ send, project }) => ({
			...send,
			project
		}));
}

export function getProjects(
	search: string | null,
	limit: number,
	before_id: number | null,
	organization_id: number | null = null
) {
	return sudoApi.get<SudoProjectsResponse>({
		endpoint: '/projects',
		data: { search, limit, before_id, organization_id }
	});
}

export function getProjectOrganizations(limit: number, before_id: number | null = null) {
	return sudoApi.get<Organization[]>({
		endpoint: '/projects/organizations',
		data: { limit, before_id }
	});
}

export function getProjectById(id: number) {
	return sudoApi.get<SudoProjectResponse>({
		endpoint: `/projects/${id}`
	});
}
