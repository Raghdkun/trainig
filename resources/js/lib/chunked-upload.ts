import {
    cancel as cancelRoute,
    chunk as chunkRoute,
    complete as completeRoute,
    init as initRoute,
} from '@/routes/training/media/uploads';

export type UploadProgress = {
    loaded: number;
    total: number;
    percentage: number;
};

type UploadableType = 'image' | 'video' | 'file';

const CHUNK_RETRIES = 3;

const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function isAbort(error: unknown, signal?: AbortSignal): boolean {
    return (
        signal?.aborted === true ||
        (error instanceof DOMException && error.name === 'AbortError')
    );
}

async function errorMessage(response: Response): Promise<string> {
    try {
        const data = (await response.clone().json()) as { message?: string };

        if (data?.message) {
            return data.message;
        }
    } catch {
        // Not JSON (e.g. an Apache 5xx HTML page) — fall through.
    }

    if (response.status === 413) {
        return 'That file is too large for the server.';
    }

    if (response.status === 419) {
        return 'Your session expired. Refresh the page and try again.';
    }

    return `Upload failed (error ${response.status}). Please try again.`;
}

/**
 * Upload one part, retrying transient (network / 5xx) failures. Client errors
 * (size, validation, expired session) fail fast — retrying won't help.
 */
async function sendChunk(
    url: string,
    blob: Blob,
    headers: Record<string, string>,
    signal?: AbortSignal,
): Promise<void> {
    let lastError: unknown;

    for (let attempt = 1; attempt <= CHUNK_RETRIES; attempt++) {
        let response: Response;

        try {
            const body = new FormData();
            body.append('chunk', blob);

            response = await fetch(url, {
                method: 'POST',
                headers,
                body,
                credentials: 'same-origin',
                signal,
            });
        } catch (error) {
            if (isAbort(error, signal)) {
                throw error;
            }

            lastError = error;

            if (attempt < CHUNK_RETRIES) {
                await delay(attempt * 500);
            }

            continue;
        }

        if (response.ok) {
            return;
        }

        const message = await errorMessage(response);

        if (response.status >= 400 && response.status < 500) {
            throw new Error(message);
        }

        lastError = new Error(message);

        if (attempt < CHUNK_RETRIES) {
            await delay(attempt * 500);
        }
    }

    throw lastError instanceof Error
        ? lastError
        : new Error('The upload failed. Please try again.');
}

/**
 * Upload a media file to a checklist item in small chunks so no single request
 * approaches the server's body-size / timeout limits. Progress is reported as
 * bytes are acknowledged; passing an aborted signal cancels and cleans up.
 */
export async function uploadMediaInChunks({
    itemId,
    type,
    label,
    file,
    onProgress,
    signal,
}: {
    itemId: number;
    type: UploadableType;
    label: string;
    file: File;
    onProgress?: (progress: UploadProgress) => void;
    signal?: AbortSignal;
}): Promise<void> {
    const headers = {
        'X-XSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const initResponse = await fetch(initRoute(itemId).url, {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify({
            type,
            label,
            filename: file.name,
            size: file.size,
        }),
        credentials: 'same-origin',
        signal,
    });

    if (!initResponse.ok) {
        throw new Error(await errorMessage(initResponse));
    }

    const { upload_id: uploadId, chunk_size: chunkSize } =
        (await initResponse.json()) as {
            upload_id: string;
            chunk_size: number;
        };

    try {
        const total = file.size;

        for (let start = 0; start < total; start += chunkSize) {
            const end = Math.min(start + chunkSize, total);
            await sendChunk(
                chunkRoute(uploadId).url,
                file.slice(start, end),
                headers,
                signal,
            );

            onProgress?.({
                loaded: end,
                total,
                percentage: Math.round((end / total) * 100),
            });
        }

        // Empty-file edge case still needs a progress tick.
        if (total === 0) {
            onProgress?.({ loaded: 0, total: 0, percentage: 100 });
        }

        const completeResponse = await fetch(completeRoute(uploadId).url, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            signal,
        });

        if (!completeResponse.ok) {
            throw new Error(await errorMessage(completeResponse));
        }
    } catch (error) {
        // Best-effort cleanup of the partial upload; keepalive lets it survive a
        // navigation away.
        void fetch(cancelRoute(uploadId).url, {
            method: 'DELETE',
            headers,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => undefined);

        throw error;
    }
}
