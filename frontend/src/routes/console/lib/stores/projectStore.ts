import { writable } from "svelte/store";
import type { Email, Project } from "../../types";

export const projectStore = writable<Project>();
export const emailStore = writable<Email[]>([]);

export function setProjectStore(project: Project) {
    projectStore.set(project);
}