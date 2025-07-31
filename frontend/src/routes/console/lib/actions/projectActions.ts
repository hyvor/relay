import type { Project } from "../../types";
import consoleApi from "../consoleApi";

export function createProject(name: string, sendType: 'transactional' | 'distributional') {
    return consoleApi.post<Project>({
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
            name,
        },
    });
}