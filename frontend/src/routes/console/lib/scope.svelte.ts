import type { Scope } from "../types";
import { getCurrentProjectUser } from "./stores/projectStore.svelte";

export function can(scope: Scope) {
    let scopes = getCurrentProjectUser().scopes;
    return scopes.includes(scope);
}

export function cant(scope: Scope) {
    return !can(scope);
}