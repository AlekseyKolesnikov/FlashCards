import { type LoaderFunctionArgs, useLoaderData, useNavigate } from 'react-router';
import { api } from '../../api';
import { setUser } from '../../auth';
import type { User } from '../../types';
import { useApiForm, useTitle } from '../../ui';
import AccountFields from '../../components/AccountFields';

/** Answers "not found" without the right token, and once an admin exists (SPECIFICATION.md §10.5). */
export async function loader({ request }: LoaderFunctionArgs) {
    const token = new URL(request.url).searchParams.get('token') ?? '';
    await api.get('/install', { token });

    return token;
}

export default function Install() {
    useTitle('Install');
    const token = useLoaderData<typeof loader>();
    const navigate = useNavigate();
    const form = useApiForm(
        (data) => api.post<{ user: User }>('/install', { ...Object.fromEntries(data), token }),
        ({ user }) => {
            setUser(user);
            navigate('/admin/languages', { replace: true, state: { notice: 'The app is installed. Add the languages of your cards first.' } });
        },
    );

    return (
        <form method="post" onSubmit={form.onSubmit} className="card form narrow">
            <h1>Install</h1>
            <p className="muted">
                This sets up the database and creates the first admin account. The page disappears once an admin exists.
            </p>
            {form.message && <p className="notice danger">{form.message}</p>}

            <AccountFields errors={form.errors} />

            <button type="submit" className="button" disabled={form.busy}>
                Install and create admin
            </button>
        </form>
    );
}
