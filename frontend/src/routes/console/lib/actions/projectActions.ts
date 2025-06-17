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