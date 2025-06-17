import { get } from "svelte/store";
import { projectStore } from "./stores/projectStore";


export function consoleUrl(path: string) {

    path = path.replace(/^\//, '');

    return '/console/' 
        + path;
}


export function consoleUrlWithNewsletter(path: string) {
    const newsletterId = get(projectStore).id;
    path = path.replace(/^\//, '');
    return consoleUrl(`${newsletterId}/${path}`)
}