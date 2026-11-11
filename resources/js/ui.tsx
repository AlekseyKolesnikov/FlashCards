// Small helpers shared by the screens.

import { type FormEvent, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { ApiError, api } from './api';
import { config } from './config';
import type { Notices } from './types';

export function useTitle(title: string): void {
    useEffect(() => {
        document.title = `${title} · ${config.appName}`;
    }, [title]);
}

/**
 * Goes to another screen, or reloads the current one (to = null), showing messages above it.
 * Going to a screen also reloads its data.
 */
export function useGo(): (to: string | null, state?: Notices & Record<string, unknown>) => void {
    const navigate = useNavigate();
    const location = useLocation();

    return (to, state) => {
        navigate(to ?? location.pathname + location.search, { replace: to === null, state: state ?? null });
    };
}

/**
 * A form sent to the API. Validation errors appear next to their fields (errors), any other
 * problem above the form (message). The form data includes the button that submitted it.
 */
export function useApiForm<T>(
    submit: (data: FormData) => Promise<T>,
    onSuccess: (result: T, data: FormData, form: HTMLFormElement) => void,
) {
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<ApiError | null>(null);
    const [busy, setBusy] = useState(false);

    async function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (busy) {
            return;
        }

        const form = event.currentTarget;
        const submitter = (event.nativeEvent as SubmitEvent).submitter;
        setBusy(true);

        try {
            const data = new FormData(form, submitter);
            const result = await submit(data);
            setErrors({});
            setError(null);
            onSuccess(result, data, form);
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            setErrors(e.fieldErrors);
            setError(e);
        } finally {
            setBusy(false);
        }
    }

    // Validation errors are shown next to their fields; other errors need the message.
    const message = error && Object.keys(errors).length === 0 ? error.message : null;

    return { onSubmit, errors, error, message, busy };
}

/** Data a component loads itself, e.g. the card form inside a dialog. */
export function useApiData<T>(path: string | null): { data: T | null; error: ApiError | null } {
    const [state, setState] = useState<{ data: T | null; error: ApiError | null }>({ data: null, error: null });

    useEffect(() => {
        if (path === null) {
            return;
        }
        let current = true;
        api.get<T>(path).then(
            (data) => current && setState({ data, error: null }),
            (error) => current && setState({ data: null, error }),
        );
        return () => {
            current = false;
        };
    }, [path]);

    return state;
}

export function FieldError({ error }: { error?: string }) {
    return error ? <p className="error">{error}</p> : null;
}

export function NoticeList({ notices }: { notices: Notices | null | undefined }) {
    if (!notices) {
        return null;
    }

    return (
        <>
            {notices.notice && <p className="notice">{notices.notice}</p>}
            {notices.warning && <p className="notice warning">{notices.warning}</p>}
            {notices.error && <p className="notice danger">{notices.error}</p>}
        </>
    );
}

/** Shortens long text for tables, like Laravel's Str::limit. */
export function limit(text: string | null | undefined, length: number): string {
    const value = text ?? '';

    return value.length > length ? `${value.slice(0, length)}…` : value;
}

export function plural(count: number, one: string, many: string): string {
    return count === 1 ? one : many;
}
