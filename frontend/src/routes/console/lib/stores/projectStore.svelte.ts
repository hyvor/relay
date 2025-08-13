import { writable } from "svelte/store";
import type { ProjectUser, Email, Project } from "../../types";

let projectUsers= $state<ProjectUser[]>([]);
let currentProjectUser = $state<ProjectUser>({} as ProjectUser);
const currentProject = $derived(currentProjectUser?.project as Project);
export const currentProjectEditing = writable<Project>();
export const emailStore = writable<Email[]>([]);

export function getProjectUsers() {
	return projectUsers;
}

export function setProjectUsers(projectUsers: ProjectUser[]) {
    projectUsers = projectUsers;
}

export function addProjectUser(projectUser: ProjectUser) {
	if (!projectUsers.find((pu) => pu.project.id === projectUser.project.id)) {
		projectUsers = [...projectUsers, projectUser];
	}
}

export function getCurrentProjectUser() {
	return currentProjectUser;
}

export function setCurrentProjectUser(projectUser: ProjectUser) {
	currentProjectUser = projectUser;
}

export function getCurrentProject() {
	return currentProject;
}

/* export function setProjectEditingStore(
	project: Partial<Project> | ((currentproject: Project) => Partial<Project>)
) {
	const stores = [projectStore, projectEditingStore];

	stores.forEach((store) => {
		store.update((b) => {
			const val = typeof project === 'function' ? project(b) : project;
			return { ...b, ...val };
		});
	});
} */
