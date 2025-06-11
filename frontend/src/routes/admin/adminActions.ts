import adminApi from "./adminApi";
import type { Server } from "./adminTypes";

export function getServers() {

    return adminApi.get<Server[]>({
        endpoint: '/servers'
    });

}