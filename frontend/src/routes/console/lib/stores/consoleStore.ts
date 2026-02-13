import { writable } from "svelte/store";
import type { AppConfig } from "../../types";
import type { CloudContextOrganization } from '@hyvor/design/cloud';

export const selectingProject = writable(false);
export const authOrganizationStore = writable<CloudContextOrganization>();

let appConfig = {} as AppConfig;

export function setAppConfig(config: AppConfig) {
    appConfig = config;
}

export function getAppConfig() {
    return appConfig;
}
