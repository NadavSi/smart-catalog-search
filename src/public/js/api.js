// Thin client for the search API. Throws ApiError with a user-facing (Hebrew) message.

export class ApiError extends Error {}

const TIMEOUT_MS = 15000;

export async function searchProducts(query, { signal } = {}) {
    const timeout = AbortSignal.timeout(TIMEOUT_MS);
    const combined = signal ? AbortSignal.any([signal, timeout]) : timeout;

    let response;
    try {
        // Relative URL: works both at the domain root and under a sub-path.
        response = await fetch(`api/search?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json' },
            signal: combined,
        });
    } catch (error) {
        if (signal?.aborted) throw error; // superseded by a newer search: let the caller ignore it
        if (timeout.aborted) throw new ApiError('החיפוש לקח יותר מדי זמן. נסו שוב.');
        throw new ApiError('אין חיבור לשרת. בדקו את החיבור לאינטרנט ונסו שוב.');
    }

    if (response.status === 429) throw new ApiError('יותר מדי חיפושים בזמן קצר. המתינו רגע ונסו שוב.');

    const body = await response.json().catch(() => null);
    if (!response.ok || !body) {
        const message = body?.errors?.q?.[0] ?? 'לא הצלחנו להשלים את החיפוש.';
        throw new ApiError(message);
    }

    return { products: body.data, meta: body.meta };
}
