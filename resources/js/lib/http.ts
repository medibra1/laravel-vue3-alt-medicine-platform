function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
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
        throw new Error(`${method} ${url} failed with status ${response.status}`);
    }

    return response.json() as Promise<TResponse>;
}

export const http = {
    post: <TResponse>(url: string, body: Record<string, unknown>) =>
        request<TResponse>('POST', url, body),
    patch: <TResponse>(url: string, body: Record<string, unknown>) =>
        request<TResponse>('PATCH', url, body),
};
