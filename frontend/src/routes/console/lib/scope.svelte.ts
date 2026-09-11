import { goto } from "$app/navigation";
import type { Scope } from "../types";
import { consoleUrlProject } from "./consoleUrl";
import { getCurrentProjectUser } from "./stores/projectStore.svelte";

export const SCOPE_MASK_MESSAGES : Partial<Record<Scope, string>> = {
    'project.write': 'You do not have permission to edit this project.',
}

export function can(scope: Scope) {
    let scopes = getCurrentProjectUser().scopes;
    return scopes.includes(scope);
}

export function cant(scope: Scope) {
    return !can(scope);
}

export function redirectIfCant(scope: Scope) {
    if (cant(scope)) {
        goto(consoleUrlProject('/'))
    }
}