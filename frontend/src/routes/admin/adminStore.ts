import { writable } from "svelte/store";
import type { IpAddress, Queue, Server } from "./adminTypes";

export const serversStore = writable<Server[]>([]);
export const ipAddressesStore = writable<IpAddress[]>([]);
export const queuesStore = writable<Queue[]>([]);