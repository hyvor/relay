import { writable } from 'svelte/store';
import type { IpAddress, Queue, Server, SudoConfig } from './sudoTypes';

export const sudoConfigStore = writable<SudoConfig>({} as SudoConfig);
export const serversStore = writable<Server[]>([]);
export const ipAddressesStore = writable<IpAddress[]>([]);
export const queuesStore = writable<Queue[]>([]);
