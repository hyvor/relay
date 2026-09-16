import type { Project, ProjectUser } from '../../types';
import consoleApi from '../consoleApi.svelte';

export function createProject(name: string, sendType: 'transactional' | 'distributional') {
	return consoleApi.post<{
		project: Project;
		project_user: ProjectUser;
	}>({
		endpoint: 'project',
		userApi: true,
		data: {
			name,
			send_type: sendType
		}
	});
}

export function updateProject(name: string) {
	return consoleApi.patch<Project>({
		endpoint: 'project',
		data: {
			name
		}
	});
}
