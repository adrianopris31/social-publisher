export type PresignedUpload = {
    key: string;
    url: string;
    headers: Record<string, string | string[]>;
};

/**
 * PUT a file straight to object storage using a presigned URL.
 *
 * Uses a raw XMLHttpRequest instead of Inertia's HTTP client: the request goes
 * to another origin (R2), so it must not carry our app headers (CSRF token,
 * X-Requested-With), and the body must be the raw file, not multipart form data.
 */
export function uploadToPresignedUrl(
    upload: PresignedUpload,
    file: File,
    contentType: string,
    onProgress: (percentage: number) => void,
): Promise<void> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open('PUT', upload.url);

        Object.entries(upload.headers).forEach(([name, value]) => {
            // The browser sets Host itself and refuses to let scripts override it.
            if (name.toLowerCase() !== 'host') {
                xhr.setRequestHeader(
                    name,
                    Array.isArray(value) ? value.join(', ') : value,
                );
            }
        });
        // Must match the ContentType the URL was signed with, or R2 answers 403.
        xhr.setRequestHeader('Content-Type', contentType);

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve();
            } else {
                reject(new Error(`Upload failed with status ${xhr.status}`));
            }
        };
        xhr.onerror = () => reject(new Error('Network error during upload'));

        xhr.send(file);
    });
}
