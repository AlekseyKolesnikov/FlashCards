import { Link, type LoaderFunctionArgs, useLoaderData, useNavigate } from 'react-router';
import { api, apiUrl } from '../../api';
import { requireUser } from '../../auth';
import { config, range } from '../../config';
import type { Language, LanguagePair } from '../../types';
import { FieldError, useApiForm, useTitle } from '../../ui';

export async function loader({ request }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    return api.get<{ languages: Language[]; pairs: LanguagePair[]; csv_columns: string[] }>('/admin/import');
}

/**
 * Start a CSV or Anki import, or download an export (SPECIFICATION.md §5.4–5.5).
 */
export default function Import() {
    useTitle('Import / export');
    const { languages, pairs, csv_columns } = useLoaderData<typeof loader>();
    const navigate = useNavigate();
    const csv = useApiForm(
        (data) => api.post('/admin/import/csv', data),
        () => navigate('/admin/import/preview'),
    );
    const anki = useApiForm(
        (data) => api.post('/admin/import/anki', data),
        () => navigate('/admin/import/mapping'),
    );

    return (
        <>
            <h1>Import / export</h1>

            <div className="panels">
                <form method="post" onSubmit={csv.onSubmit} className="card form">
                    <h2>Import CSV</h2>
                    <p className="hint">UTF-8, comma, semicolon or tab separated. The first row names the columns:</p>
                    <pre className="code">{csv_columns.join(',')}</pre>
                    <p className="hint">
                        <code>lang1</code>, <code>word1</code>, <code>lang2</code> and <code>word2</code> are required. Languages are
                        codes from the <Link to="/admin/languages">language list</Link>. Empty complexity means{' '}
                        {config.complexity.default}.
                    </p>
                    {csv.message && <p className="notice danger">{csv.message}</p>}

                    <label htmlFor="csv-file">File</label>
                    <input id="csv-file" name="file" type="file" accept=".csv,.txt" required />
                    <FieldError error={csv.errors.file} />

                    <button type="submit" className="button" disabled={csv.busy}>
                        Check file
                    </button>
                </form>

                <form method="post" onSubmit={anki.onSubmit} className="card form">
                    <h2>Import from Anki</h2>
                    <p className="hint">
                        In Anki, use File → Export → “Notes in Plain Text (.txt)”. You choose which column is which on the next page.
                    </p>
                    {anki.message && <p className="notice danger">{anki.message}</p>}

                    <label htmlFor="anki-file">File</label>
                    <input id="anki-file" name="file" type="file" accept=".txt,.csv,.tsv" required />
                    <FieldError error={anki.errors.file} />

                    <div className="row">
                        {[1, 2].map((side) => (
                            <div key={side} className="grow">
                                <label htmlFor={`anki-lang${side}`}>Language {side}</label>
                                <select id={`anki-lang${side}`} name={`lang${side}_id`} required defaultValue="">
                                    <option value="">Choose…</option>
                                    {languages.map((language) => (
                                        <option key={language.id} value={language.id}>
                                            {language.name} ({language.code})
                                        </option>
                                    ))}
                                </select>
                                <FieldError error={anki.errors[`lang${side}_id`]} />
                            </div>
                        ))}
                    </div>

                    <label htmlFor="anki-complexity">Complexity for all cards</label>
                    <select id="anki-complexity" name="complexity" className="short" defaultValue={config.complexity.default}>
                        {range(config.complexity).map((value) => (
                            <option key={value} value={value}>
                                {value}
                            </option>
                        ))}
                    </select>

                    <button type="submit" className="button" disabled={anki.busy}>
                        Next: choose columns
                    </button>
                </form>

                {/* A plain form: the browser downloads the file from the API. */}
                <form method="get" action={apiUrl('/admin/export')} className="card form">
                    <h2>Export</h2>

                    <label htmlFor="export-pair">Cards</label>
                    <select id="export-pair" name="pair">
                        <option value="">All language pairs</option>
                        {pairs.map((pair) => (
                            <option key={pair.key} value={pair.key}>
                                {pair.label}
                            </option>
                        ))}
                    </select>

                    <label htmlFor="export-format">Format</label>
                    <select id="export-format" name="format">
                        <option value="csv">CSV (opens in Excel, can be imported here)</option>
                        <option value="anki">Anki plain text (.txt)</option>
                    </select>

                    <button type="submit" className="button">
                        Download
                    </button>
                </form>
            </div>
        </>
    );
}
