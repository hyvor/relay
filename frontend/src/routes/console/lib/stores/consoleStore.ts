import { writable } from "svelte/store";
import type { AppConfig } from "../../types";

export const selectingProject = writable(false);

let appConfig = {} as AppConfig;

export function setAppConfig(config: AppConfig) {
    appConfig = config;
}

export function getAppConfig() {
    return appConfig;
}
