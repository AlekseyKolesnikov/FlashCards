import { redirect } from 'react-router';
import { ApiError, api } from '../../api';
import type { Message } from '../../types';

/**
 * Loads a step of the import in progress. Without one (404), or when its file cannot be
 * read (422, which also ends the import), the admin goes back to the start of the import.
 */
export async function loadImportStep<T>(path: string, query?: URLSearchParams): Promise<T | { failed: string }> {
    try {
        return await api.get<T>(path, query);
    } catch (e) {
        if (e instanceof ApiError && e.status === 404) {
            throw redirect('/admin/import');
        }
        if (e instanceof ApiError && e.status === 422) {
            return { failed: e.message };
        }
        throw e;
    }
}

export function cancelImport(): Promise<Message> {
    return api.delete<Message>('/admin/import');
}
