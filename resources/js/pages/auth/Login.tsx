import { useRef, useState } from 'react';
import { Link, type LoaderFunctionArgs, redirect, useNavigate, useSearchParams } from 'react-router';
import { ApiError, api } from '../../api';
import { getUser, homePath, setUser } from '../../auth';
import { config } from '../../config';
import type { Message, User } from '../../types';
import { FieldError, useApiForm, useTitle } from '../../ui';

export function loader({ request }: LoaderFunctionArgs) {
    const user = getUser();

    return user ? redirect(safeNext(new URL(request.url).searchParams.get('next')) ?? homePath(user)) : null;
}

/** Only paths inside the app, never another site. */
function safeNext(next: string | null): string | null {
    return next && next.startsWith('/') && !next.startsWith('//') ? next : null;
}

export default function Login() {
    useTitle('Log in');
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const credentials = useRef<FormData | null>(null);
    const [resend, setResend] = useState<{ busy: boolean; message?: string; error?: string }>({ busy: false });

    const form = useApiForm(
        (data) => {
            credentials.current = data;
            setResend({ busy: false });
            return api.post<{ user: User }>('/login', Object.fromEntries(data));
        },
        ({ user }) => {
            setUser(user);
            navigate(safeNext(params.get('next')) ?? homePath(user), { replace: true });
        },
    );
    const notActivated = form.error?.data?.code === 'not_activated';

    // Sends a new activation link, proving ownership with the username and password just typed (§9.1).
    async function sendLinkAgain() {
        setResend({ busy: true });
        try {
            const { message } = await api.post<Message>('/email/resend', Object.fromEntries(credentials.current!));
            setResend({ busy: false, message });
        } catch (e) {
            setResend({ busy: false, error: e instanceof ApiError ? e.message : 'The link could not be sent.' });
        }
    }

    return (
        <form method="post" onSubmit={form.onSubmit} className="card form narrow">
            <h1>Log in</h1>

            {params.has('activated') && <p className="notice">Your account is activated. You can log in now.</p>}
            {params.get('activation') === 'invalid' && (
                <p className="notice danger">This activation link has expired or is not valid. Log in to get a new one.</p>
            )}

            {notActivated && (
                <div className="notice warning">
                    <p>{form.error!.message}</p>
                    {resend.message ? (
                        <p>{resend.message}</p>
                    ) : (
                        <button type="button" className="button secondary" onClick={sendLinkAgain} disabled={resend.busy}>
                            Send the link again
                        </button>
                    )}
                    {resend.error && <p className="error">{resend.error}</p>}
                </div>
            )}
            {form.message && !notActivated && <p className="notice danger">{form.message}</p>}

            <label htmlFor="username">Username</label>
            <input id="username" name="username" required autoFocus autoComplete="username" />
            <FieldError error={form.errors.username} />

            <label htmlFor="password">Password</label>
            <input id="password" name="password" type="password" required autoComplete="current-password" />
            <FieldError error={form.errors.password} />

            <button type="submit" className="button" disabled={form.busy}>
                Log in
            </button>

            {config.registrationOpen && (
                <p className="muted">
                    No account? <Link to="/register">Register</Link>
                </p>
            )}
        </form>
    );
}
