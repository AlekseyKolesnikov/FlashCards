import { Link, type LoaderFunctionArgs, useLoaderData } from 'react-router';
import { api } from '../../api';
import { requireUser } from '../../auth';
import type { Language, Message } from '../../types';
import { FieldError, useApiForm, useGo, useTitle } from '../../ui';

export async function loader({ request, params }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    return (await api.get<{ language: Language }>(`/admin/languages/${params.id}`)).language;
}

export default function LanguageEdit() {
    useTitle('Edit language');
    const language = useLoaderData<typeof loader>();
    const go = useGo();
    const form = useApiForm(
        (data) => api.put<Message>(`/admin/languages/${language.id}`, Object.fromEntries(data)),
        ({ message }) => go('/admin/languages', { notice: message }),
    );

    return (
        <form method="post" onSubmit={form.onSubmit} className="card form narrow">
            <h1>Edit language</h1>

            <label htmlFor="code">Code</label>
            <input id="code" name="code" defaultValue={language.code} required maxLength={10} />
            <p className="hint">Used in CSV files. Changing it does not affect existing cards.</p>
            <FieldError error={form.errors.code} />

            <label htmlFor="name">Name</label>
            <input id="name" name="name" defaultValue={language.name} required maxLength={64} />
            <FieldError error={form.errors.name} />

            <div className="buttons">
                <button type="submit" className="button" disabled={form.busy}>
                    Save
                </button>
                <Link to="/admin/languages">Cancel</Link>
            </div>
        </form>
    );
}
