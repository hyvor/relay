import { writable } from "svelte/store";
import type { Email, Project } from "../../types";

export const projectStore = writable<Project>();
export const projectEditingStore = writable<Project>();
export const emailStore = writable<Email[]>([]);

export function setProjectStore(project: Project) {
    projectStore.set(project);
}

export function setProjectEditingStore(
	project: Partial<Project> | ((currentproject: Project) => Partial<Project>)
) {
	const stores = [projectStore, projectEditingStore];

	stores.forEach((store) => {
		store.update((b) => {
			const val = typeof project === 'function' ? project(b) : project;
			return { ...b, ...val };
		});
	});
}
