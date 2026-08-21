function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

/**
 * Carries the Laravel validation error bag (field -> first message) so
 * callers can show something more useful than a bare status code.
 */
export class HttpError extends Error {
    status: number;
    errors: Record<string, string[]>;

    constructor(message: string, status: number, errors: Record<string, string[]>) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

async function request<TResponse>(
    method: 'POST' | 'PATCH',
    url: string,
    body: Record<string, unknown>,
): Promise<TResponse> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new HttpError(
            data?.message ?? `${method} ${url} failed with status ${response.status}`,
            response.status,
            data?.errors ?? {},
        );
    }

    return response.json() as Promise<TResponse>;
}

export const http = {
    post: <TResponse>(url: string, body: Record<string, unknown>) =>
        request<TResponse>('POST', url, body),
    patch: <TResponse>(url: string, body: Record<string, unknown>) =>
        request<TResponse>('PATCH', url, body),
};
