import { getCurrentProjectUser } from "./stores/projectStore.svelte";

export interface ConsoleApiOptions {
    endpoint: string,
    data?: Record<string, any> | FormData,
    userApi?: boolean,
    publicApi?: boolean,
    projectId?: string,
    signal?: AbortSignal,
}

interface CallOptions extends ConsoleApiOptions {
    method: 'get' | 'post' | 'patch' | 'delete' | 'put'
}

function getConsoleApi() {

    const consoleBaseUrl = "/api/console/";

    async function call<T>({
        endpoint,
        method,
        userApi = false,
        data = {},
        projectId,
        signal
    }: CallOptions): Promise<T> {

        let url = consoleBaseUrl + endpoint.replace(/^\//, '');

        if (method === 'get') {
            url +=
                '?' +
                Object.entries(data)
                    .filter(([, val]) => val !== null && val !== undefined)
                    .map(([key, val]) => key + '=' + encodeURIComponent(val))
                    .join('&');
        }

        const headers = {} as Record<string, string>;

        if (!userApi) {
            headers['X-Project-ID'] = getCurrentProjectUser().project.id.toString();
        }
        else if (projectId) {
            headers['X-Project-ID'] = projectId;
        }

        if (!(data instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }


        const options = {
            cache: 'no-cache',
            credentials: 'same-origin',
            method: method.toUpperCase(),
            headers,
            signal
        } as RequestInit;

        if (method !== 'get') {
            options.body = data instanceof FormData ? data : JSON.stringify(data);
        }

        const response = await fetch(url, options)

        if (!response.ok) {
            const e = await response.json();
            const error = e && e.message ? e.message : 'Something went wrong';
            /* toast({type: 'error', message: error});
            throw error; */

            const toThrow = new Error(error) as any;
            toThrow.message = error;
            toThrow.code = response.status;
            toThrow.data = e && e.data ? e.data : null;


            if (e.violations) {
                toThrow.message = e.violations.map((v: any) => v.property + ': ' + v.message).join(', ');
            }

            throw toThrow;
        }

        const json = await response.json();
        return json as T;

    }

    return {
        call,
        get: async <T>(opt: ConsoleApiOptions) => call<T>({ ...opt, method: 'get' }),
        post: async <T>(opt: ConsoleApiOptions) => call<T>({ ...opt, method: 'post' }),
        patch: async <T>(opt: ConsoleApiOptions) => call<T>({ ...opt, method: 'patch' }),
        put: async <T>(opt: ConsoleApiOptions) => call<T>({ ...opt, method: 'put' }),
        delete: async <T>(opt: ConsoleApiOptions) => call<T>({ ...opt, method: 'delete' }),
    }

}

const consoleApi = getConsoleApi();
export default consoleApi;
