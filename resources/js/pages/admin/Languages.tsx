import { useState } from 'react';
import { Link, type LoaderFunctionArgs, useLoaderData, useLocation } from 'react-router';
import { ApiError, api } from '../../api';
import { requireUser } from '../../auth';
import type { Language, Message } from '../../types';
import { FieldError, useApiForm, useGo, useTitle } from '../../ui';

interface LanguageStats extends Language {
    cards_count: number;
    /** Card count per complexity. */
    by_complexity: Record<string, number>;
}

interface LanguageList {
    languages: LanguageStats[];
    complexities: number[];
    totals: Record<string, number>;
}

export async function loader({ request }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    return api.get<LanguageList>('/admin/languages');
}

/** Language list with card counts per complexity (SPECIFICATION.md §4.2). */
export default function Languages() {
    useTitle('Languages');
    const { languages, complexities, totals } = useLoaderData<typeof loader>();
    const location = useLocation();
    const go = useGo();
    const [error, setError] = useState<string | null>(null);
    const form = useApiForm(
        (data) => api.post<Message>('/admin/languages', Object.fromEntries(data)),
        ({ message }) => go(null, { notice: message }),
    );

    async function deleteLanguage(language: Language) {
        if (!confirm(`Delete ${language.name}?`)) {
            return;
        }
        try {
            const { message } = await api.delete<Message>(`/admin/languages/${language.id}`);
            go(null, { notice: message });
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            setError(e.message);
        }
    }

    const sum = (counts: Record<string, number>) => Object.values(counts).reduce((a, b) => a + b, 0);

    return (
        <>
            <h1>Languages</h1>
            {error && <p className="notice danger">{error}</p>}

            {languages.length === 0 ? (
                <p className="muted">No languages yet.</p>
            ) : (
                <div className="table-wrap">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Cards</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {languages.map((language) => (
                                <tr key={language.id}>
                                    <td>{language.code}</td>
                                    <td>{language.name}</td>
                                    <td>{language.cards_count}</td>
                                    <td className="actions">
                                        <Link to={`/admin/languages/${language.id}`}>Edit</Link>
                                        {language.cards_count === 0 ? (
                                            <button type="button" className="link-button danger" onClick={() => deleteLanguage(language)}>
                                                Delete
                                            </button>
                                        ) : (
                                            <span className="muted" title="Languages used by cards cannot be deleted">
                                                In use
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {complexities.length > 0 && (
                <>
                    <h2>Words by complexity</h2>
                    <div className="table-wrap">
                        <table className="table stats">
                            <thead>
                                <tr>
                                    <th>Language</th>
                                    {complexities.map((complexity) => (
                                        <th key={complexity} className="num">
                                            {complexity}
                                        </th>
                                    ))}
                                    <th className="num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {languages
                                    .filter((language) => language.cards_count > 0)
                                    .map((language) => (
                                        <tr key={language.id}>
                                            <td>{language.name}</td>
                                            {complexities.map((complexity) => (
                                                <td key={complexity} className="num">
                                                    {language.by_complexity[complexity] ?? ''}
                                                </td>
                                            ))}
                                            <td className="num">{sum(language.by_complexity)}</td>
                                        </tr>
                                    ))}
                                <tr className="total">
                                    <td>All cards</td>
                                    {complexities.map((complexity) => (
                                        <td key={complexity} className="num">
                                            <Link to={`/admin/cards?complexity=${complexity}`}>{totals[complexity]}</Link>
                                        </td>
                                    ))}
                                    <td className="num">{sum(totals)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p className="hint">Each card has two languages, so it is counted in both language rows.</p>
                </>
            )}

            {/* Keyed by the visit, so the fields are empty again after adding a language. */}
            <form key={location.key} method="post" onSubmit={form.onSubmit} className="card form inline-form">
                <h2>Add a language</h2>
                <div className="row">
                    <div>
                        <label htmlFor="code">Code</label>
                        <input id="code" name="code" required maxLength={10} placeholder="de" />
                        <FieldError error={form.errors.code} />
                    </div>
                    <div className="grow">
                        <label htmlFor="name">Name</label>
                        <input id="name" name="name" required maxLength={64} placeholder="German" />
                        <FieldError error={form.errors.name} />
                    </div>
                    <button type="submit" className="button" disabled={form.busy}>
                        Add
                    </button>
                </div>
                <p className="hint">
                    The code is used in CSV files, e.g. <code>en</code>, <code>de</code>, <code>pt-br</code>.
                </p>
            </form>
        </>
    );
}
