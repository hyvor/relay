import adminApi from "./adminApi";
import type { IpAddress, Server } from "./adminTypes";

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