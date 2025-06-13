
export interface ConsoleApiOptions {
    endpoint: string,
    data?: Record<string, any> | FormData,
    signal?: AbortSignal,
}

interface CallOptions extends ConsoleApiOptions {
    method: 'get' | 'post' | 'patch' | 'delete' | 'put'
}

function getAdminApi() {

    const apiBaseUrl = "/api/admin/";

    async function call<T>({
        endpoint,
        method,
        data = {},
        signal
    }: CallOptions): Promise<T> {

        let url = apiBaseUrl + endpoint.replace(/^\//, '');

        if (method === 'get') {
            url +=
                '?' +
                Object.entries(data)
                    .filter(([, val]) => val !== null && val !== undefined)
                    .map(([key, val]) => key + '=' + encodeURIComponent(val))
                    .join('&');
        }

        const headers = {} as Record<string, string>;

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

            const toThrow = new Error(error) as any;
            toThrow.message = error;
            toThrow.code = e && e.code ? e.code : 500;

            if (e.violations) {
                toThrow.message = e.violations.map((v: any) => v.message).join(', ');
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
        delete: async <T>(opt: ConsoleApiOptions) => call<T>({ ...opt, method: 'delete' }),
    }

}

const adminApi = getAdminApi();
export default adminApi;
