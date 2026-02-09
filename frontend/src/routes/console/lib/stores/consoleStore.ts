import { writable } from "svelte/store";
import type { AppConfig, Organization } from "../../types";

export const selectingProject = writable(false);
export const authUserOrganizationStore = writable<Organization | null>();

let appConfig = {} as AppConfig;

export function setAppConfig(config: AppConfig) {
    appConfig = config;
}

export function getAppConfig() {
    return appConfig;
}
