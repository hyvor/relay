import { writable } from "svelte/store";
import type { ProjectUser, Email, Project } from "../../types";

let projectUsers= $state<ProjectUser[]>([]);
let currentProjectUser = $state<ProjectUser>({} as ProjectUser);
const currentProject = $derived(currentProjectUser?.project as Project);
let currentProjectEditing = $state<Project>({} as Project);
export const emailStore = writable<Email[]>([]);

export function getProjectUsers() {
	return projectUsers;
}

export function setProjectUsers(pu: ProjectUser[]) {
    projectUsers = pu;
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
	currentProjectEditing = projectUser.project;
}

export function getCurrentProject() {
	return currentProject;
}

export function setCurrentProject(project: Project) {
	currentProjectUser.project = project;
}

export function getCurrentProjectEditing() {
	return currentProjectEditing;
}

export function setCurrentProjectEditing(project: Project) {
	currentProjectEditing = project;
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
