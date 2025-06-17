import { writable } from "svelte/store";
import type { Project } from "../../types";

export const userProjectStore = writable<Project[]>([]);

export function addUserProject(project: Project) {
    userProjectStore.update((projects) => [...projects, project]);
}
