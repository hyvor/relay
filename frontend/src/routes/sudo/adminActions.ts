import adminApi from "./adminApi";
import type { IpAddress, Queue, Server } from "./adminTypes";

export function getServers() {
    return adminApi.get<Server[]>({
        endpoint: '/servers'
    });
}

export function getIpAddresses() {
    return adminApi.get<IpAddress[]>({
        endpoint: "/ip-addresses"
    });
}

export function getQueues() {
    return adminApi.get<Queue[]>({
        endpoint: "/queues"
    });
}