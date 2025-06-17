import { writable } from "svelte/store";
import type { Project } from "../../types";

export const projectStore = writable<Project>();

export function setProjectStore(project: Project) {
    projectStore.set(project);
}