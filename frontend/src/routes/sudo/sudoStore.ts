import { writable } from 'svelte/store';
import type {
	DnsRecord,
	DefaultDnsRecord,
	Instance,
	IpAddress,
	Queue,
	Server,
	SudoConfig,
	WarmupSchedule
} from './sudoTypes';

export const sudoConfigStore = writable<SudoConfig>({} as SudoConfig);
export const instanceStore = writable<Instance>({} as Instance);
export const serversStore = writable<Server[]>([]);
export const ipAddressesStore = writable<IpAddress[]>([]);
export const queuesStore = writable<Queue[]>([]);
export const dnsRecordsStore = writable<DnsRecord[]>([]);
export const defaultDnsRecordsStore = writable<DefaultDnsRecord[]>([]);
export const warmupSchedulesStore = writable<WarmupSchedule[]>([]);
