import { writable } from 'svelte/store';
import type { Instance, IpAddress, Queue, Server, SudoConfig } from './sudoTypes';

export const sudoConfigStore = writable<SudoConfig>({} as SudoConfig);
export const instanceStore = writable<Instance>({} as Instance);
export const serversStore = writable<Server[]>([]);
export const ipAddressesStore = writable<IpAddress[]>([]);
export const queuesStore = writable<Queue[]>([]);
