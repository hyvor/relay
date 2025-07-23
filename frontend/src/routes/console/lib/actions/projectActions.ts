import type { Project } from "../../types";
import consoleApi from "../consoleApi";

export function createProject(name: string) {
    return consoleApi.post<Project>({
        endpoint: 'project',
        userApi: true,
        data: {
            name,
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