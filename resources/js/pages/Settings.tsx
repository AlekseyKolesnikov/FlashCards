import { useState } from 'react';
import { type LoaderFunctionArgs } from 'react-router';
import { api } from '../api';
import { requireUser, setUser, useUser } from '../auth';
import { config } from '../config';
import type { Message, User } from '../types';
import { FieldError, useApiForm, useTitle } from '../ui';

export function loader({ request }: LoaderFunctionArgs) {
    requireUser(request);

    return null;
}

/** Learner settings (SPECIFICATION.md §9.2) and password change (§9.1). */
export default function Settings() {
    useTitle('Settings');
    const user = useUser()!;
    const { sessionSize, postponeSessions } = config;
    const [saved, setSaved] = useState<string | null>(null);
    const [passwordChanged, setPasswordChanged] = useState<string | null>(null);

    const settings = useApiForm(
        (data) => api.put<{ user: User; message: string }>('/settings', Object.fromEntries(data)),
        (result) => {
            setUser(result.user);
            setSaved(result.message);
        },
    );
    const password = useApiForm(
        (data) => api.put<Message>('/settings/password', Object.fromEntries(data)),
        ({ message }, _data, form) => {
            form.reset();
            setPasswordChanged(message);
        },
    );

    return (
        <>
            <form method="post" onSubmit={settings.onSubmit} className="card form narrow">
                <h1>Settings</h1>
                <p className="muted">
                    {user.username}
                    {user.email && ` · ${user.email}`}
                </p>
                {saved && <p className="notice">{saved}</p>}
                {settings.message && <p className="notice danger">{settings.message}</p>}

                <label htmlFor="session_size">Words per session</label>
                <input
                    id="session_size"
                    name="session_size"
                    type="number"
                    required
                    min={sessionSize.min}
                    max={sessionSize.max}
                    defaultValue={user.session_size}
                />
                <p className="hint">
                    {sessionSize.min}–{sessionSize.max}. Applies from your next new session.
                </p>
                <FieldError error={settings.errors.session_size} />

                <label htmlFor="postpone_sessions">Learned words postponed for (sessions)</label>
                <input
                    id="postpone_sessions"
                    name="postpone_sessions"
                    type="number"
                    required
                    min={postponeSessions.min}
                    max={postponeSessions.max}
                    defaultValue={user.postpone_sessions}
                />
                <p className="hint">
                    {postponeSessions.min}–{postponeSessions.max}. A learned word comes back after this many sessions.
                </p>
                <FieldError error={settings.errors.postpone_sessions} />

                <button type="submit" className="button" disabled={settings.busy}>
                    Save
                </button>
            </form>

            <form method="post" onSubmit={password.onSubmit} className="card form narrow inline-form">
                <h2>Change password</h2>
                {passwordChanged && <p className="notice">{passwordChanged}</p>}
                {password.message && <p className="notice danger">{password.message}</p>}

                <label htmlFor="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" required autoComplete="current-password" />
                <FieldError error={password.errors.current_password} />

                <label htmlFor="password">New password</label>
                <input id="password" name="password" type="password" required autoComplete="new-password" minLength={8} />
                <p className="hint">At least 8 characters.</p>
                <FieldError error={password.errors.password} />

                <label htmlFor="password_confirmation">Repeat new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autoComplete="new-password" />

                <button type="submit" className="button" disabled={password.busy}>
                    Change password
                </button>
            </form>
        </>
    );
}
