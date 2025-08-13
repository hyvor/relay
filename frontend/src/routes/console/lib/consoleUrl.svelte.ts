import { getCurrentProject } from "./stores/projectStore.svelte";


export function consoleUrl(path: string) {

    path = path.replace(/^\//, '');

    return '/console/' 
        + path;
}


export function consoleUrlProject(path: string) {
    const projectId = getCurrentProject().id;
    path = path.replace(/^\//, '');
    return consoleUrl(`${projectId}/${path}`)
}