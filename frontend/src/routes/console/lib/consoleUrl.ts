import { get } from "svelte/store";
import { projectStore } from "./stores/projectStore";


export function consoleUrl(path: string) {

    path = path.replace(/^\//, '');

    return '/console/' 
        + path;
}


export function consoleUrlProject(path: string) {
    const projectId = get(projectStore).id;
    path = path.replace(/^\//, '');
    return consoleUrl(`${projectId}/${path}`)
}