import { useState } from 'react';
import { Link, redirect } from 'react-router';
import { api } from '../../api';
import { getUser, homePath } from '../../auth';
import { config } from '../../config';
import type { Message } from '../../types';
import { useApiForm, useTitle } from '../../ui';
import AccountFields from '../../components/AccountFields';

export function loader() {
    const user = getUser();
    if (user) {
        return redirect(homePath(user));
    }
    if (!config.registrationOpen) {
        throw new Response('Not found', { status: 404 });
    }

    return null;
}

/** The account must be activated by email before it can log in (SPECIFICATION.md §9.1). */
export default function Register() {
    useTitle('Register');
    const [sent, setSent] = useState<string | null>(null);
    const form = useApiForm(
        (data) => api.post<Message>('/register', Object.fromEntries(data)),
        ({ message }) => setSent(message),
    );

    if (sent) {
        return (
            <div className="card narrow">
                <h1>Check your email</h1>
                <p>{sent}</p>
                <p className="muted">
                    The link works for 24 hours. Then you can <Link to="/login">log in</Link>.
                </p>
            </div>
        );
    }

    return (
        <form method="post" onSubmit={form.onSubmit} className="card form narrow">
            <h1>Create a learner account</h1>
            {form.message && <p className="notice danger">{form.message}</p>}

            <AccountFields errors={form.errors} />
            <p className="hint">We send a link to this address to activate your account.</p>

            <button type="submit" className="button" disabled={form.busy}>
                Register
            </button>

            <p className="muted">
                Already registered? <Link to="/login">Log in</Link>
            </p>
        </form>
    );
}
